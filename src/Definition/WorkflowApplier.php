<?php

namespace RoBYCoNTe\FilamentFlow\Definition;

use Illuminate\Support\Facades\DB;
use RoBYCoNTe\FilamentFlow\Definition\Enums\MutationClass;
use RoBYCoNTe\FilamentFlow\Definition\Planning\PlanOptions;
use RoBYCoNTe\FilamentFlow\Definition\Planning\WorkflowPlanner;
use RoBYCoNTe\FilamentFlow\Exceptions\WorkflowConflictException;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotification;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationChannel;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationRecipient;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationTemplate;
use RoBYCoNTe\FilamentFlow\Models\WorkflowScheduledCheck;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateFieldRole;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionSideEffect;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionValidationRule;
use RoBYCoNTe\FilamentFlow\Revision\WorkflowSnapshotService;

/**
 * Reconciles a WorkflowDefinition with the database. Model-agnostic: a workflow
 * is identified by (tenantId, modelType, stateColumn). Breaking changes snapshot
 * the current definition before mutating it.
 */
final class WorkflowApplier
{
    public function __construct(private readonly WorkflowPlanner $planner) {}

    public function apply(
        string $modelType,
        ?int $tenantId,
        WorkflowDefinition $definition,
        ?PlanOptions $options = null,
        ?int $revisionVersion = null,
    ): Workflow {
        $options ??= PlanOptions::make();

        $existing = Workflow::query()
            ->where('tenant_id', $tenantId)
            ->where('model_type', $modelType)
            ->where('state_column', $definition->getStateColumn())
            ->first();

        $plan = $this->planner->plan($definition, $existing, $options);

        if (! $plan->isApplicable()) {
            throw new WorkflowConflictException($plan);
        }

        if ($plan->isClean()) {
            return $existing;
        }

        return DB::transaction(function () use ($modelType, $tenantId, $definition, $existing, $plan, $options, $revisionVersion): Workflow {
            $isCreate = $existing === null;
            $workflow = $existing ?? new Workflow;

            $workflow->fill([
                'tenant_id' => $tenantId,
                'name' => $definition->getName(),
                'model_type' => $modelType,
                'state_column' => $definition->getStateColumn(),
                'is_active' => $definition->isActive(),
                'creation_policy' => $definition->getCreationPolicy() ?: null,
                'metadata' => $definition->getMetadata() ?: null,
            ])->save();

            if (! $isCreate && $plan->mutationClass() === MutationClass::Breaking) {
                app(WorkflowSnapshotService::class)->snapshot(
                    $workflow,
                    ['changes' => $plan->toArray()['changes']],
                    $options->getUserId(),
                    $revisionVersion,
                );
            }

            $stateIds = $this->syncStates($workflow, $definition);
            $this->syncTransitions($workflow, $definition, $stateIds);
            $this->syncScheduledChecks($workflow, $definition, $stateIds);
            $this->syncNotifications($workflow, null, null, $definition->getNotifications());
            $this->removeObsolete($workflow, $definition);

            return $workflow->refresh();
        });
    }

    /** @return array<string,int> state name → state id */
    private function syncStates(Workflow $workflow, WorkflowDefinition $definition): array
    {
        $ids = [];

        foreach ($definition->getStates() as $index => $state) {
            $data = $state->toArray();

            $row = WorkflowState::query()->firstOrNew([
                'workflow_id' => $workflow->id,
                'name' => $state->name(),
            ]);

            $row->fill([
                'label' => $data['label'],
                'color' => $data['color'],
                'icon' => $data['icon'],
                'description' => $data['description'],
                'is_initial' => $data['is_initial'],
                'is_final' => $data['is_final'],
                'metadata' => $data['metadata'] ?: null,
            ]);
            $row->sort_order = $index;
            $row->save();

            $ids[$state->name()] = $row->id;

            WorkflowStateField::query()->where('state_id', $row->id)->delete();

            foreach ($state->stateFields() as $fieldIndex => $field) {
                $attributes = $field->toArray();

                $stateField = WorkflowStateField::create([
                    'state_id' => $row->id,
                    'field_name' => $attributes['field_name'],
                    'visibility' => $attributes['visibility'],
                    'mutability' => $attributes['mutability'],
                    'is_required' => $attributes['is_required'],
                    'validation_rules' => $attributes['validation_rules'],
                    'sort_order' => $fieldIndex,
                ]);

                foreach ($attributes['role_overrides'] as $override) {
                    WorkflowStateFieldRole::create([
                        'state_field_id' => $stateField->id,
                        'role_name' => $override['role_name'],
                        'visibility' => $override['visibility'],
                        'mutability' => $override['mutability'],
                        'is_required' => $override['is_required'],
                    ]);
                }
            }

            $this->syncAccessRules($row, $state);
            $this->syncNotifications($workflow, $row->id, null, $state->stateNotifications());
        }

        return $ids;
    }

    /** @param array<string,int> $stateIds */
    private function syncTransitions(Workflow $workflow, WorkflowDefinition $definition, array $stateIds): void
    {
        foreach ($definition->getTransitions() as $transition) {
            $data = $transition->toArray();

            $row = WorkflowTransition::query()->firstOrNew([
                'workflow_id' => $workflow->id,
                'name' => $transition->name(),
            ]);

            $row->fill([
                'from_state_id' => $transition->from() !== null ? ($stateIds[$transition->from()] ?? null) : null,
                'to_state_id' => $transition->to() !== null ? ($stateIds[$transition->to()] ?? null) : null,
                'label' => $data['label'],
                'description' => $data['description'],
                'requires_confirmation' => $data['requires_confirmation'],
                'requires_reason' => $data['requires_reason'],
                'validation_level' => $data['validation_level'] ?? 'full',
                'conditions' => $data['conditions'] ?: null,
                'metadata' => $data['metadata'] ?: null,
            ])->save();

            WorkflowTransitionSideEffect::query()->where('transition_id', $row->id)->delete();

            foreach ($transition->effects() as $effectIndex => $effect) {
                $attributes = $effect->toArray();
                $attributes['sort_order'] = $effectIndex;

                WorkflowTransitionSideEffect::create($attributes + ['transition_id' => $row->id]);
            }

            WorkflowTransitionValidationRule::query()->where('transition_id', $row->id)->delete();

            foreach ($transition->rules() as $ruleIndex => $rule) {
                $attributes = $rule->toArray();
                $attributes['sort_order'] = $ruleIndex;

                WorkflowTransitionValidationRule::create($attributes + ['transition_id' => $row->id]);
            }

            $this->syncNotifications($workflow, null, $row->id, $transition->transitionNotifications());
        }
    }

    private function syncAccessRules(WorkflowState $row, State $state): void
    {
        $kept = [];

        foreach ($state->stateAccessRules() as $rule) {
            $data = $rule->toArray();

            $accessRule = WorkflowStateAccessRule::query()->firstOrNew([
                'state_id' => $row->id,
                'access_type' => $data['access_type'],
                'rule' => $data['rule'],
                'operator' => $data['operator'],
            ]);

            $accessRule->fill([
                'priority' => $data['priority'],
                'is_active' => $data['is_active'],
                'metadata' => $data['metadata'] ?: null,
            ])->save();

            $kept[] = $accessRule->id;
        }

        WorkflowStateAccessRule::query()
            ->where('state_id', $row->id)
            ->whereNotIn('id', $kept)
            ->delete();
    }

    /** @param array<string,int> $stateIds */
    private function syncScheduledChecks(Workflow $workflow, WorkflowDefinition $definition, array $stateIds): void
    {
        $names = [];

        foreach ($definition->getScheduledChecks() as $check) {
            $data = $check->toArray();
            $names[] = $check->name();

            $row = WorkflowScheduledCheck::query()->firstOrNew([
                'workflow_id' => $workflow->id,
                'name' => $check->name(),
            ]);

            $row->fill([
                'description' => $data['description'],
                'state_id' => $data['state'] !== null ? ($stateIds[$data['state']] ?? null) : null,
                'condition_type' => $data['condition_type'],
                'condition_config' => $data['condition_config'],
                'action_type' => $data['action_type'],
                'action_config' => $data['action_config'],
                'frequency' => $data['frequency'],
                'once_per_record' => $data['once_per_record'],
                'is_active' => $data['is_active'],
            ])->save();
        }

        WorkflowScheduledCheck::query()
            ->where('workflow_id', $workflow->id)
            ->whereNotIn('name', $names)
            ->delete();
    }

    /**
     * Reconciles the notifications of one scope (workflow, state or transition)
     * plus their recipients, channels and per-channel templates.
     *
     * @param  list<Notification>  $notifications
     */
    private function syncNotifications(
        Workflow $workflow,
        ?int $stateId,
        ?int $transitionId,
        array $notifications,
    ): void {
        $kept = [];

        foreach ($notifications as $notification) {
            $data = $notification->toArray();

            $row = WorkflowNotification::query()->firstOrNew([
                'workflow_id' => $workflow->id,
                'name' => $notification->name(),
                'state_id' => $stateId,
                'transition_id' => $transitionId,
            ]);

            $row->fill([
                'state_id' => $stateId,
                'transition_id' => $transitionId,
                'trigger_event' => $data['trigger_event'],
                'description' => $data['description'],
                'is_active' => $data['is_active'],
                'timing' => $data['timing'],
                'delay_minutes' => $data['delay_minutes'],
                'priority' => $data['priority'],
                'metadata' => $data['metadata'] ?: null,
            ])->save();

            $kept[] = $row->id;

            $row->recipients()->delete();

            foreach ($data['recipients'] as $index => $recipient) {
                WorkflowNotificationRecipient::create([
                    'notification_id' => $row->id,
                    'recipient_type' => $recipient['recipient_type'],
                    'recipient_config' => $recipient['recipient_config'] ?: null,
                    'sort_order' => $recipient['sort_order'] ?? $index,
                ]);
            }

            $row->channels()->delete();
            $row->templates()->delete();

            foreach ($data['channels'] as $channel) {
                $channelRow = WorkflowNotificationChannel::create([
                    'notification_id' => $row->id,
                    'channel_type' => $channel['channel_type'],
                    'channel_config' => $channel['channel_config'] ?: null,
                    'is_active' => $channel['is_active'],
                ]);

                WorkflowNotificationTemplate::create([
                    'notification_id' => $row->id,
                    'channel_id' => $channelRow->id,
                    'subject' => $data['template']['subject'],
                    'title' => $data['template']['title'],
                    'body' => $data['template']['body'],
                    'action_text' => $data['template']['action_text'],
                    'action_url' => $data['template']['action_url'],
                    'template_engine' => $data['template']['template_engine'],
                    'format' => $data['template']['format'],
                    'variables' => $data['template']['variables'] ?: null,
                ]);
            }
        }

        WorkflowNotification::query()
            ->where('workflow_id', $workflow->id)
            ->where('state_id', $stateId)
            ->where('transition_id', $transitionId)
            ->whereNotIn('id', $kept)
            ->delete();
    }

    private function removeObsolete(Workflow $workflow, WorkflowDefinition $definition): void
    {
        $transitionNames = array_map(static fn (Transition $t): string => $t->name(), $definition->getTransitions());
        $stateNames = array_map(static fn (State $s): string => $s->name(), $definition->getStates());

        WorkflowTransition::query()
            ->where('workflow_id', $workflow->id)
            ->whereNotIn('name', $transitionNames)
            ->delete();

        WorkflowState::query()
            ->where('workflow_id', $workflow->id)
            ->whereNotIn('name', $stateNames)
            ->delete();
    }
}
