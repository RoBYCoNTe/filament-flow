<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Definition;

use Illuminate\Support\Facades\DB;
use RoBYCoNTe\FilamentFlow\Definition\AccessRule;
use RoBYCoNTe\FilamentFlow\Definition\Enums\MutationClass;
use RoBYCoNTe\FilamentFlow\Definition\Notification;
use RoBYCoNTe\FilamentFlow\Definition\Planning\WorkflowPlanner;
use RoBYCoNTe\FilamentFlow\Definition\Recipient;
use RoBYCoNTe\FilamentFlow\Definition\ScheduledCheck;
use RoBYCoNTe\FilamentFlow\Definition\State;
use RoBYCoNTe\FilamentFlow\Definition\Transition;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowApplier;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowDefinition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotification;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationChannel;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationRecipient;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationTemplate;
use RoBYCoNTe\FilamentFlow\Models\WorkflowScheduledCheck;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Services\ConditionEvaluator;
use RoBYCoNTe\FilamentFlow\Services\ScheduledCheckRunner;
use RoBYCoNTe\FilamentFlow\Services\SideEffectExecutor;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class WorkflowExtendedApplierTest extends TestCase
{
    private const TENANT = 3;

    public function test_persists_scheduled_checks_with_resolved_state(): void
    {
        $workflow = $this->applier()->apply(Order::class, self::TENANT, $this->definition());

        $check = WorkflowScheduledCheck::where('workflow_id', $workflow->id)->where('name', 'overdue')->firstOrFail();
        $state = WorkflowState::where('workflow_id', $workflow->id)->where('name', 'under_review')->firstOrFail();

        $this->assertSame($state->id, $check->state_id);
        $this->assertSame('date_offset', $check->condition_type);
        $this->assertSame('daily', $check->frequency);
        $this->assertTrue($check->once_per_record);
        $this->assertSame(['to_state' => 'rejected', 'force' => true], $check->action_config);
    }

    public function test_persists_access_rules_and_notifications_with_children(): void
    {
        $workflow = $this->applier()->apply(Order::class, self::TENANT, $this->definition());

        $state = WorkflowState::where('workflow_id', $workflow->id)->where('name', 'under_review')->firstOrFail();
        $transition = WorkflowTransition::where('workflow_id', $workflow->id)->where('name', 'approve')->firstOrFail();

        $this->assertSame(2, WorkflowStateAccessRule::where('state_id', $state->id)->count());
        $this->assertSame(1, WorkflowStateAccessRule::where('state_id', $state->id)->where('rule', 'role:reviewer')->count());

        $stateNotification = WorkflowNotification::where('state_id', $state->id)->where('name', 'review-started')->firstOrFail();
        $this->assertSame('on_state_enter', $stateNotification->trigger_event);
        $this->assertSame(1, WorkflowNotificationRecipient::where('notification_id', $stateNotification->id)->count());

        $transitionNotification = WorkflowNotification::where('transition_id', $transition->id)->where('name', 'approved-notice')->firstOrFail();
        $this->assertSame('on_transition', $transitionNotification->trigger_event);
        $this->assertSame(2, WorkflowNotificationChannel::where('notification_id', $transitionNotification->id)->count());

        // One template per channel, same content.
        $templates = WorkflowNotificationTemplate::where('notification_id', $transitionNotification->id)->get();
        $this->assertSame(2, $templates->count());
        $this->assertSame(['Approved', 'Approved'], $templates->pluck('title')->all());
        $this->assertSame('markdown', $templates->first()->format);
    }

    public function test_reapplying_the_same_definition_is_clean(): void
    {
        $applier = $this->applier();
        $workflow = $applier->apply(Order::class, self::TENANT, $this->definition());

        $plan = app(WorkflowPlanner::class)
            ->plan($this->definition(), $workflow);

        $this->assertTrue($plan->isClean(), $plan->summary());
        $this->assertSame(1, $workflow->fresh()->schema_version);
    }

    public function test_updates_are_safe_and_removals_are_breaking(): void
    {
        $workflow = $this->applier()->apply(Order::class, self::TENANT, $this->definition());

        $updated = $this->definition();
        $updated->scheduledChecks([ScheduledCheck::make('overdue', 'Overdue')->hourly()->whenDateOffset('due_date')]);

        $plan = app(WorkflowPlanner::class)->plan($updated, $workflow);

        $this->assertNotNull($plan->changesOf('updated_scheduled_check')[0] ?? null);
        $this->assertNotNull($plan->changesOf('removed_scheduled_check')[0] ?? null);

        $this->applier()->apply(Order::class, self::TENANT, $updated);

        $check = WorkflowScheduledCheck::where('workflow_id', $workflow->id)->where('name', 'overdue')->firstOrFail();
        $this->assertSame('hourly', $check->frequency);
        $this->assertSame(1, WorkflowScheduledCheck::where('workflow_id', $workflow->id)->count());
    }

    public function test_removed_access_rule_is_breaking(): void
    {
        $withRule = WorkflowDefinition::make('order-rules', Order::class)
            ->state(State::make('draft', 'Draft')->initial()->accessRule(AccessRule::view(AccessRule::role('reviewer'))));

        $workflow = $this->applier()->apply(Order::class, self::TENANT, $withRule);

        $withoutRule = WorkflowDefinition::make('order-rules', Order::class)
            ->state(State::make('draft', 'Draft')->initial());

        $plan = app(WorkflowPlanner::class)->plan($withoutRule, $workflow);

        $removed = $plan->changesOf('removed_access_rule')[0] ?? null;

        $this->assertNotNull($removed);
        $this->assertSame(MutationClass::Breaking, $removed->mutationClass);

        $this->applier()->apply(Order::class, self::TENANT, $withoutRule);

        $this->assertSame(0, WorkflowStateAccessRule::count());
    }

    public function test_snapshot_includes_the_new_families(): void
    {
        $workflow = $this->applier()->apply(Order::class, self::TENANT, $this->definition());

        // Removing the "under_review" state is breaking and triggers a snapshot.
        $reduced = WorkflowDefinition::make('order', Order::class)
            ->state(State::make('draft', 'Draft')->initial())
            ->state(State::make('rejected', 'Rejected')->final());

        $this->applier()->apply(Order::class, self::TENANT, $reduced);

        $snapshot = json_decode(
            (string) DB::table('workflow_snapshots')->where('workflow_id', $workflow->id)->value('snapshot'),
            true,
        );

        $this->assertArrayHasKey('scheduled_checks', $snapshot);
        $this->assertArrayHasKey('access_rules', $snapshot);
        $this->assertArrayHasKey('notifications', $snapshot);
        $this->assertArrayHasKey('notification_templates', $snapshot);
        $this->assertNotEmpty($snapshot['scheduled_checks']);
    }

    public function test_runner_resolves_references_by_name(): void
    {
        $workflow = $this->applier()->apply(Order::class, self::TENANT, $this->definition());

        $check = WorkflowScheduledCheck::where('workflow_id', $workflow->id)->where('name', 'reminder')->firstOrFail();
        $notification = WorkflowNotification::where('workflow_id', $workflow->id)->where('name', 'approved-notice')->firstOrFail();
        $transition = WorkflowTransition::where('workflow_id', $workflow->id)->where('name', 'approve')->firstOrFail();

        $runner = new class(app(ConditionEvaluator::class), app(SideEffectExecutor::class)) extends ScheduledCheckRunner
        {
            /** @param array<string,mixed> $config */
            public function notificationId(WorkflowScheduledCheck $check, array $config): ?int
            {
                return $this->resolveNotificationId($check, $config);
            }

            /** @param array<string,mixed> $config */
            public function transitionId(WorkflowScheduledCheck $check, array $config): ?int
            {
                return $this->resolveTransitionId($check, $config);
            }
        };

        $this->assertSame($notification->id, $runner->notificationId($check, $check->action_config));
        $this->assertSame($transition->id, $runner->transitionId($check, ['transition_name' => 'approve']));
        $this->assertSame('weekly', $check->frequency);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function applier(): WorkflowApplier
    {
        return app(WorkflowApplier::class);
    }

    private function definition(): WorkflowDefinition
    {
        return WorkflowDefinition::make('order-extended', Order::class)
            ->state(
                State::make('draft', 'Draft')->initial()
                    ->accessRule(AccessRule::view(AccessRule::ANY))
            )
            ->state(
                State::make('under_review', 'Under review')
                    ->accessRule(AccessRule::view(AccessRule::role('reviewer')))
                    ->accessRule(AccessRule::transition(AccessRule::OWNER))
                    ->notification(
                        Notification::make('review-started', 'Review started')
                            ->database()
                            ->recipient(Recipient::role('reviewer'))
                            ->title('Review started')
                            ->body('The order is under review.')
                    )
            )
            ->state(State::make('rejected', 'Rejected')->final())
            ->transition(
                Transition::make('approve', 'under_review', 'rejected')
                    ->notification(
                        Notification::make('approved-notice', 'Approved notice')
                            ->database()
                            ->mail()
                            ->recipient(Recipient::recordOwner())
                            ->title('Approved')
                            ->body('Your order was approved.')
                            ->format('markdown')
                    )
            )
            ->scheduledCheck(
                ScheduledCheck::make('overdue', 'Overdue')
                    ->state('under_review')
                    ->daily()
                    ->oncePerRecord()
                    ->whenDateOffset('due_date', -2)
                    ->thenTransition('rejected')
            )
            ->scheduledCheck(
                ScheduledCheck::make('reminder')
                    ->weekly()
                    ->whenCustomClass('App\\Checks\\Reminder')
                    ->thenNotificationNamed('approved-notice')
            );
    }
}
