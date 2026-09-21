<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Planning;

use RoBYCoNTe\FilamentFlow\Definition\Enums\MutationClass;
use RoBYCoNTe\FilamentFlow\Definition\State;
use RoBYCoNTe\FilamentFlow\Definition\Transition;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowDefinition;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;

/**
 * Diffs a WorkflowDefinition against the persisted workflow. Read-only: it never
 * touches the database, so it is safe to call for previews and for agents.
 */
final class WorkflowPlanner
{
    use DiffsWorkflowDefinition;
    use ProjectsWorkflowRows;

    public function plan(
        WorkflowDefinition $definition,
        ?Workflow $existing = null,
        ?PlanOptions $options = null,
    ): WorkflowChangePlan {
        $options ??= PlanOptions::make();

        /** @var array<string,State> $desiredStates */
        $desiredStates = [];
        foreach ($definition->getStates() as $state) {
            $desiredStates[$state->name()] = $state;
        }

        /** @var array<string,Transition> $desiredTransitions */
        $desiredTransitions = [];
        foreach ($definition->getTransitions() as $transition) {
            $desiredTransitions[$transition->name()] = $transition;
        }

        $changes = [];
        $conflicts = [];

        $this->validateReferences($desiredStates, $desiredTransitions, $conflicts);

        if ($existing === null) {
            foreach (array_keys($desiredStates) as $name) {
                $changes[] = new WorkflowChange('added_state', $name, MutationClass::Additive);
            }
            foreach (array_keys($desiredTransitions) as $name) {
                $changes[] = new WorkflowChange('added_transition', $name, MutationClass::Additive);
            }

            return new WorkflowChangePlan($definition, null, $changes, $conflicts);
        }

        $existingStates = WorkflowState::query()->where('workflow_id', $existing->id)->get()->keyBy('name');
        $statesById = $existingStates->keyBy('id');
        $existingTransitions = WorkflowTransition::query()->where('workflow_id', $existing->id)->get()->keyBy('name');

        // states
        foreach ($desiredStates as $name => $state) {
            $row = $existingStates->get($name);

            if ($row === null) {
                $changes[] = new WorkflowChange('added_state', $name, MutationClass::Additive);

                continue;
            }

            if ($this->stateChanged($row, $state)) {
                $changes[] = new WorkflowChange('updated_state', $name, MutationClass::Safe);
            }

            if ($this->stateFieldsChanged($row, $state)) {
                $changes[] = new WorkflowChange('updated_state_fields', $name, MutationClass::Safe);
            }
        }

        $removedStates = [];
        foreach ($existingStates as $name => $row) {
            if (isset($desiredStates[$name])) {
                continue;
            }

            $removedStates[$name] = true;
            $changes[] = new WorkflowChange('removed_state', $name, MutationClass::Breaking);
        }

        // transitions
        foreach ($desiredTransitions as $name => $transition) {
            $row = $existingTransitions->get($name);

            if ($row === null) {
                $changes[] = new WorkflowChange('added_transition', $name, MutationClass::Additive);

                continue;
            }

            if ($this->transitionChanged($row, $transition, $statesById)) {
                $changes[] = new WorkflowChange('updated_transition', $name, MutationClass::Safe);
            }
        }

        foreach ($existingTransitions as $name => $row) {
            if (isset($desiredTransitions[$name])) {
                continue;
            }

            $changes[] = new WorkflowChange('removed_transition', $name, MutationClass::Breaking);
        }

        // scheduled checks (workflow level)
        $this->diffScheduledChecks($existing, $definition, $statesById, $changes);

        foreach ($desiredStates as $name => $state) {
            $row = $existingStates->get($name);

            if ($row === null) {
                continue;
            }

            $this->diffAccessRules($name, $row, $state, $changes);
            $this->diffNotifications($existing, $name, $row->id, null, $state->stateNotifications(), $changes);
        }

        foreach ($desiredTransitions as $name => $transition) {
            $row = $existingTransitions->get($name);

            if ($row === null) {
                continue;
            }

            $this->diffNotifications($existing, $name, null, $row->id, $transition->transitionNotifications(), $changes);
        }

        $this->diffNotifications($existing, null, null, null, $definition->getNotifications(), $changes);

        // conflicts: a removed state still referenced by a surviving transition
        if ($removedStates !== []) {
            foreach ($existingTransitions as $name => $row) {
                if (! isset($desiredTransitions[$name])) {
                    continue;
                }

                foreach ([$row->from_state_id, $row->to_state_id] as $stateId) {
                    $stateName = $stateId !== null ? ($statesById->get($stateId)?->name) : null;

                    if ($stateName !== null && isset($removedStates[$stateName]) && ! $options->isForced()) {
                        $conflicts[] = [
                            'code' => 'state_in_use',
                            'message' => "State [{$stateName}] cannot be removed: transition [{$name}] still references it.",
                            'key' => $stateName,
                        ];
                    }
                }
            }
        }

        return new WorkflowChangePlan($definition, $existing, $changes, $conflicts);
    }
}
