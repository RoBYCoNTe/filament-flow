<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Planning;

use Illuminate\Support\Collection;
use RoBYCoNTe\FilamentFlow\Definition\Enums\MutationClass;
use RoBYCoNTe\FilamentFlow\Definition\Notification;
use RoBYCoNTe\FilamentFlow\Definition\State;
use RoBYCoNTe\FilamentFlow\Definition\Transition;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowDefinition;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotification;
use RoBYCoNTe\FilamentFlow\Models\WorkflowScheduledCheck;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateFieldRole;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionSideEffect;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionValidationRule;

/**
 * Comparing a declared workflow with the one in the database.
 *
 * Every row that exists is compared field by field with what the definition asks for, and
 * every difference becomes a change with a name. Declared references are checked on the way,
 * so a workflow that points at a state that does not exist cannot be applied.
 */
trait DiffsWorkflowDefinition
{
    /**
     * @param  array<string,State>  $states
     * @param  array<string,Transition>  $transitions
     * @param  list<array{code:string,message:string,key:string}>  $conflicts
     */
    private function validateReferences(array $states, array $transitions, array &$conflicts): void
    {
        foreach ($transitions as $transition) {
            foreach ([$transition->from(), $transition->to()] as $endpoint) {
                if ($endpoint !== null && ! isset($states[$endpoint])) {
                    $conflicts[] = [
                        'code' => 'unknown_state',
                        'message' => "Transition [{$transition->name()}] references unknown state [{$endpoint}].",
                        'key' => $transition->name(),
                    ];
                }
            }
        }
    }

    /**
     * @param  Collection<int,WorkflowState>  $statesById
     * @param  list<WorkflowChange>  $changes
     */
    private function diffScheduledChecks(Workflow $workflow, WorkflowDefinition $definition, $statesById, array &$changes): void
    {
        $existing = WorkflowScheduledCheck::query()
            ->where('workflow_id', $workflow->id)
            ->get()
            ->mapWithKeys(fn (WorkflowScheduledCheck $row): array => [
                $row->name => $this->projectScheduledCheck($row, $statesById),
            ])
            ->all();

        $desired = [];
        foreach ($definition->getScheduledChecks() as $check) {
            $desired[$check->name()] = $check->toArray();
        }

        $this->diffRows($existing, $desired, 'scheduled_check', null, $changes);
    }

    /** @param list<WorkflowChange> $changes */
    private function diffAccessRules(string $stateName, WorkflowState $row, State $state, array &$changes): void
    {
        $existing = WorkflowStateAccessRule::query()
            ->where('state_id', $row->id)
            ->get()
            ->mapWithKeys(fn (WorkflowStateAccessRule $rule): array => [
                $rule->access_type.'|'.$rule->rule.'|'.$rule->operator => $this->projectAccessRule($rule),
            ])
            ->all();

        $desired = [];
        foreach ($state->stateAccessRules() as $rule) {
            $desired[$rule->key()] = $rule->toArray();
        }

        $this->diffRows($existing, $desired, 'access_rule', $stateName, $changes);
    }

    /**
     * Diffs the notifications attached to the workflow itself ($stateId and
     * $transitionId both null), to a state or to a transition.
     *
     * @param  list<Notification>  $desired
     * @param  list<WorkflowChange>  $changes
     */
    private function diffNotifications(
        Workflow $workflow,
        ?string $scope,
        ?int $stateId,
        ?int $transitionId,
        array $desired,
        array &$changes,
    ): void {
        $existing = WorkflowNotification::query()
            ->where('workflow_id', $workflow->id)
            ->where('state_id', $stateId)
            ->where('transition_id', $transitionId)
            ->get()
            ->mapWithKeys(fn (WorkflowNotification $row): array => [
                $row->name => $this->projectNotification($row),
            ])
            ->all();

        $rows = [];
        foreach ($desired as $notification) {
            $rows[$notification->name()] = $notification->toArray();
        }

        $this->diffRows($existing, $rows, 'notification', $scope, $changes);
    }

    /**
     * @param  array<string,array<string,mixed>>  $existing  key => canonical row
     * @param  array<string,array<string,mixed>>  $desired
     * @param  list<WorkflowChange>  $changes
     */
    private function diffRows(array $existing, array $desired, string $type, ?string $scope, array &$changes): void
    {
        $prefix = $scope !== null ? $scope.'/' : '';

        foreach ($desired as $key => $row) {
            if (! isset($existing[$key])) {
                $changes[] = new WorkflowChange('added_'.$type, $prefix.$key, MutationClass::Additive);

                continue;
            }

            if ($this->encode([$existing[$key]]) !== $this->encode([$row])) {
                $changes[] = new WorkflowChange('updated_'.$type, $prefix.$key, MutationClass::Safe);
            }
        }

        foreach ($existing as $key => $row) {
            if (isset($desired[$key])) {
                continue;
            }

            $changes[] = new WorkflowChange('removed_'.$type, $prefix.$key, MutationClass::Breaking);
        }
    }

    private function stateChanged(WorkflowState $row, State $state): bool
    {
        $data = $state->toArray();

        return $row->label !== $data['label']
            || $row->color !== $data['color']
            || (bool) $row->is_initial !== (bool) $data['is_initial']
            || (bool) $row->is_final !== (bool) $data['is_final'];
    }

    private function stateFieldsChanged(WorkflowState $row, State $state): bool
    {
        $existing = WorkflowStateField::query()
            ->where('state_id', $row->id)
            ->with('roleOverrides')
            ->get()
            ->map(function (WorkflowStateField $f): array {
                $row = $this->clean(
                    $f->toArray(),
                    ['id', 'state_id', 'created_at', 'updated_at', 'sort_order', 'role_overrides'],
                );

                $row['role_overrides'] = $f->roleOverrides
                    ->map(fn (WorkflowStateFieldRole $r): array => [
                        'role_name' => $r->role_name,
                        'visibility' => $r->visibility,
                        'mutability' => $r->mutability,
                        'is_required' => $r->is_required === null ? null : (bool) $r->is_required,
                    ])
                    ->sortBy('role_name')
                    ->values()
                    ->all();

                return $row;
            })
            ->sortBy('field_name')
            ->values()
            ->all();

        $desired = array_map(
            fn ($f): array => $this->clean($f->toArray(), ['sort_order']),
            $state->stateFields(),
        );
        usort($desired, static fn ($a, $b) => $a['field_name'] <=> $b['field_name']);

        return $this->encode($existing) !== $this->encode($desired);
    }

    private function transitionChanged(WorkflowTransition $row, Transition $transition, $statesById): bool
    {
        $data = $transition->toArray();

        $fromName = $row->from_state_id !== null ? $statesById->get($row->from_state_id)?->name : null;
        $toName = $row->to_state_id !== null ? $statesById->get($row->to_state_id)?->name : null;

        if ($fromName !== $transition->from() || $toName !== $transition->to()) {
            return true;
        }

        $scalars = [
            'label' => $data['label'],
            'description' => $data['description'],
            'requires_confirmation' => (bool) $data['requires_confirmation'],
            'requires_reason' => (bool) $data['requires_reason'],
        ];

        foreach ($scalars as $attribute => $value) {
            if ($this->normalize($row->{$attribute}) !== $this->normalize($value)) {
                return true;
            }
        }

        // How much a transition checks, and what it expects of the caller, are part of
        // the declaration as much as its label is: without them here a call could be
        // loosened — a draft that stops validating, a step that stops requiring every
        // field — and the database would keep answering with the old rule.
        if ($row->validation_level->value !== ($data['validation_level'] ?? null)) {
            return true;
        }

        // An empty set of metadata is one thing written two ways — the column may hold
        // `null`, the definition may hold `[]` — and a difference that is not one would
        // keep every transition permanently out of date.
        if ($this->normalize((array) $row->metadata) !== $this->normalize((array) ($data['metadata'] ?? []))) {
            return true;
        }

        // Same rule as the metadata: a set that is empty may be written `null` in the
        // column and `[]` in the definition, and that is not a difference.
        if ($this->normalize((array) $row->conditions) !== $this->normalize((array) $data['conditions'])) {
            return true;
        }

        $existingEffects = WorkflowTransitionSideEffect::query()
            ->where('transition_id', $row->id)
            ->get()
            // `toArray()` and not `getAttributes()`: the raw attributes keep a JSON column
            // as the string it is stored in, while the definition holds the decoded value,
            // and comparing the two would make every row look different forever.
            ->map(fn (WorkflowTransitionSideEffect $e): array => $this->clean($e->toArray(), ['id', 'transition_id', 'created_at', 'updated_at', 'sort_order']))
            ->sortBy('field_name')
            ->values()
            ->all();

        $desiredEffects = array_map(fn ($e): array => $this->clean($e->toArray(), ['sort_order']), $transition->effects());
        usort($desiredEffects, static fn ($a, $b) => $a['field_name'] <=> $b['field_name']);

        if ($this->encode($existingEffects) !== $this->encode($desiredEffects)) {
            return true;
        }

        $existingRules = WorkflowTransitionValidationRule::query()
            ->where('transition_id', $row->id)
            ->get()
            // The field type belongs to the form, not to the rule: the table of rules does not
            // keep it, and comparing it would be an eternal drift.
            ->map(fn (WorkflowTransitionValidationRule $r): array => $this->clean($r->toArray(), ['id', 'transition_id', 'created_at', 'updated_at', 'sort_order', 'field_type']))
            ->sortBy('field_name')
            ->values()
            ->all();

        $desiredRules = array_map(fn ($r): array => $this->clean($r->toArray(), ['sort_order', 'field_type']), $transition->rules());
        usort($desiredRules, static fn ($a, $b) => $a['field_name'] <=> $b['field_name']);

        return $this->encode($existingRules) !== $this->encode($desiredRules);
    }
}
