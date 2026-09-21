<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Definition;

use RoBYCoNTe\FilamentFlow\Definition\AccessRule;
use RoBYCoNTe\FilamentFlow\Definition\Enums\AccessOperator;
use RoBYCoNTe\FilamentFlow\Definition\Enums\AccessType;
use RoBYCoNTe\FilamentFlow\Definition\Enums\NotificationChannel;
use RoBYCoNTe\FilamentFlow\Definition\Enums\NotificationPriority;
use RoBYCoNTe\FilamentFlow\Definition\Enums\NotificationTiming;
use RoBYCoNTe\FilamentFlow\Definition\Enums\NotificationTrigger;
use RoBYCoNTe\FilamentFlow\Definition\Enums\RecipientType;
use RoBYCoNTe\FilamentFlow\Definition\Enums\ScheduledCheckAction;
use RoBYCoNTe\FilamentFlow\Definition\Enums\ScheduledCheckCondition;
use RoBYCoNTe\FilamentFlow\Definition\Enums\ScheduledCheckFrequency;
use RoBYCoNTe\FilamentFlow\Definition\Notification;
use RoBYCoNTe\FilamentFlow\Definition\Recipient;
use RoBYCoNTe\FilamentFlow\Definition\ScheduledCheck;
use RoBYCoNTe\FilamentFlow\Definition\State;
use RoBYCoNTe\FilamentFlow\Definition\Transition;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowDefinition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class WorkflowExtendedNodesTest extends TestCase
{
    public function test_access_rules_round_trip(): void
    {
        $definition = WorkflowDefinition::make('order', Order::class)
            ->state(
                State::make('under_review', 'Under review')
                    ->accessRule(AccessRule::view(AccessRule::role('reviewer')))
                    ->accessRule(AccessRule::transition(AccessRule::OWNER)->and()->priority(10)->active())
                    ->accessRule(AccessRule::edit(AccessRule::permission('edit-orders')))
            );

        $this->assertSame($definition->toArray(), WorkflowDefinition::fromArray($definition->toArray())->toArray());

        $rules = $definition->getStates()[0]->stateAccessRules();

        $this->assertCount(3, $rules);
        $this->assertSame(AccessType::View, $rules[0]->accessType());
        $this->assertSame('role:reviewer', $rules[0]->rule());
        $this->assertSame(AccessOperator::And->value, $rules[1]->toArray()['operator']);
        $this->assertSame(10, $rules[1]->toArray()['priority']);
        $this->assertSame(AccessType::Edit, $rules[2]->accessType());
    }

    public function test_access_rule_key_is_stable(): void
    {
        $rule = AccessRule::transition(AccessRule::role('reviewer'));

        $this->assertSame('transition|role:reviewer|or', $rule->key());
        $this->assertSame($rule->key(), AccessRule::fromArray($rule->toArray())->key());
    }

    public function test_scheduled_checks_round_trip(): void
    {
        $definition = WorkflowDefinition::make('order', Order::class)
            ->scheduledCheck(
                ScheduledCheck::make('overdue', 'Overdue reminders')
                    ->state('under_review')
                    ->everyFiveMinutes()
                    ->oncePerRecord()
                    ->whenDateOffset('due_date', -2, '<=')
                    ->thenTransition('rejected', force: false)
            )
            ->scheduledCheck(
                ScheduledCheck::make('notify-reminder')
                    ->daily()
                    ->whenFieldCompare([['field' => 'status', 'operator' => '=', 'value' => 'open']])
                    ->thenNotificationNamed('review-reminder')
            );

        $this->assertSame($definition->toArray(), WorkflowDefinition::fromArray($definition->toArray())->toArray());

        $checks = $definition->getScheduledChecks();

        $this->assertCount(2, $checks);
        $this->assertSame(ScheduledCheckFrequency::EveryFiveMinutes->value, $checks[0]->toArray()['frequency']);
        $this->assertSame(ScheduledCheckCondition::DateOffset->value, $checks[0]->toArray()['condition_type']);
        $this->assertSame(ScheduledCheckAction::Transition->value, $checks[0]->toArray()['action_type']);
        $this->assertFalse($checks[0]->toArray()['action_config']['force']);
        $this->assertSame(['notification_name' => 'review-reminder'], $checks[1]->toArray()['action_config']);
    }

    public function test_notifications_round_trip_with_recipients_channels_and_template(): void
    {
        $definition = WorkflowDefinition::make('order', Order::class)
            ->transition(
                Transition::make('submit', 'draft', 'submitted')->notification(
                    Notification::make('submit-notice', 'Submission notice')
                        ->database()
                        ->mail(['from' => 'noreply@example.com'])
                        ->recipient(Recipient::role('reviewer', 'admin'))
                        ->recipient(Recipient::recordOwner())
                        ->subject('Order {{order_number}} submitted')
                        ->title('Submitted')
                        ->body('Order {{order_number}} was submitted.')
                        ->action('https://example.com/orders/{{id}}', 'Open')
                        ->priority(NotificationPriority::High)
                        ->delay(30)
                        ->templateEngine('mustache')
                        ->format('markdown')
                        ->variables(['order_number'])
                        ->metadata(['source' => 'sdk'])
                )
            );

        $this->assertSame($definition->toArray(), WorkflowDefinition::fromArray($definition->toArray())->toArray());

        $notification = $definition->getTransitions()[0]->transitionNotifications()[0]->toArray();

        $this->assertSame(NotificationTrigger::OnTransition->value, $notification['trigger_event']);
        $this->assertSame(NotificationTiming::Delayed->value, $notification['timing']);
        $this->assertSame(30, $notification['delay_minutes']);
        $this->assertSame(2, count($notification['recipients']));
        $this->assertSame(['roles' => ['reviewer', 'admin']], $notification['recipients'][0]['recipient_config']);
        $this->assertSame(RecipientType::RecordOwner->value, $notification['recipients'][1]['recipient_type']);
        $this->assertSame(
            [NotificationChannel::Database->value, NotificationChannel::Mail->value],
            array_column($notification['channels'], 'channel_type'),
        );
        $this->assertSame('mustache', $notification['template']['template_engine']);
        $this->assertSame('markdown', $notification['template']['format']);
        $this->assertSame(['order_number'], $notification['template']['variables']);
    }

    public function test_attachment_sets_the_right_trigger_event(): void
    {
        $state = State::make('submitted', 'Submitted')
            ->notification(Notification::make('enter'))
            ->onExitNotification(Notification::make('exit'));

        $this->assertSame(NotificationTrigger::OnStateEnter, $state->stateNotifications()[0]->triggerEvent());
        $this->assertSame(NotificationTrigger::OnStateExit, $state->stateNotifications()[1]->triggerEvent());

        $transition = Transition::make('submit', 'draft', 'submitted')
            ->notification(Notification::make('notice'));

        $this->assertSame(NotificationTrigger::OnTransition, $transition->transitionNotifications()[0]->triggerEvent());
    }

    public function test_notification_defaults_to_a_database_channel(): void
    {
        $notification = Notification::make('plain')->toArray();

        $this->assertSame([NotificationChannel::Database->value], array_column($notification['channels'], 'channel_type'));
        $this->assertSame(NotificationTiming::Immediate->value, $notification['timing']);
        $this->assertNull($notification['delay_minutes']);
    }
}
