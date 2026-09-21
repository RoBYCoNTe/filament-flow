<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;

/**
 * The subject of a validation: which record, which workflow, which state.
 *
 * A payload is validated against the state it is leaving — the rules of the state being left
 * are the ones that apply — and the same lookup answers both questions.
 */
trait ResolvesValidationContext
{
    /**
     * The record as seen from the state that is being validated: entering a
     * state means the rules of *that* state apply.
     */
    private function subjectIn(Model $record, ?string $targetState): Model
    {
        if ($targetState === null) {
            return $record;
        }

        $subject = clone $record;
        $subject->setAttribute('state', $targetState);

        return $subject;
    }

    /**
     * Workflow of a record, for callers that need to know whether validation
     * applies at all.
     */
    public function workflowFor(Model $record): ?Workflow
    {
        $tenantId = method_exists($record, 'getWorkflowTenantId') ? $record->getWorkflowTenantId() : null;
        $column = 'state';

        return Workflow::findForModel(get_class($record), $column, $tenantId);
    }

    public function stateFor(Model $record, ?string $stateName = null): ?WorkflowState
    {
        $workflow = $this->workflowFor($record);

        if ($workflow === null) {
            return null;
        }

        $value = $stateName ?? $record->{$workflow->state_column};

        return $workflow->states()
            ->where('name', $value)
            ->orWhere('class_name', $value)
            ->first();
    }
}
