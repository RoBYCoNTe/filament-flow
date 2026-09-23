<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Exception;
use Illuminate\Support\Facades\Auth;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionMetadata;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionSnapshot;
use Spatie\ModelStates\State;

/**
 * Transition history of a model: the state transition row (with its snapshot and
 * metadata) and the operator notes. Composed into HasDatabaseTransitions; it
 * relies on the workflow helpers provided there.
 */
trait LogsTransitionHistory
{
    /**
     * Log the transition to the workflow_state_transitions table
     * Supports both Database-First (with Workflow) and Code-First (without Workflow) approaches
     */
    protected function logTransition(State|string $fromState, State|string $toState, string $field, ?WorkflowTransition $knownTransition = null): void
    {
        if (! config('filament-flow.enabled', true)) {
            return;
        }

        try {
            // Determine from and to state classes
            $fromStateClass = is_string($fromState) ? $fromState : get_class($fromState);
            $toStateClass = is_string($toState) ? $toState : get_class($toState);

            // Try to get workflow for this model (Database-First approach, with tenant
            // fallback)
            $workflow = Workflow::findForModel(static::class, $field, $this->getWorkflowTenantId());

            // Initialize variables for workflow-related data
            $workflowId = null;
            $transitionId = null;
            $fromStateLabel = null;
            $toStateLabel = null;

            if ($workflow) {
                // Database-First approach: get labels from workflow states
                $fromWorkflowState = $this->getWorkflowState($workflow, $fromStateClass);
                $toWorkflowState = $this->getWorkflowState($workflow, $toStateClass);

                $workflowId = $workflow->id;
                $fromStateLabel = $fromWorkflowState?->getAttribute('label');
                $toStateLabel = $toWorkflowState?->getAttribute('label');

                // Use known transition if provided, otherwise find it
                $transitionConfig = $knownTransition ?? $this->findTransitionConfig($workflow, $fromWorkflowState, $toWorkflowState);
                $transitionId = $transitionConfig?->id;
            } else {
                // Code-First approach: try to get labels from State classes
                if ($fromState instanceof State && method_exists($fromState, 'getLabel')) {
                    $fromStateLabel = $fromState->getLabel();
                }
                if ($toState instanceof State && method_exists($toState, 'getLabel')) {
                    $toStateLabel = $toState->getLabel();
                } elseif (is_string($toState)) {
                    // Try to instantiate the state class to get the label
                    try {
                        if (class_exists($toState)) {
                            $tempState = new $toState($this);
                            if (method_exists($tempState, 'getLabel')) {
                                $toStateLabel = $tempState->getLabel();
                            }
                        }
                    } catch (Exception) {
                        // Ignore if we can't get the label
                    }
                }
            }

            // Get current user
            $user = Auth::user();

            // Extract transition notes if enabled
            $notes = $this->extractTransitionNotes();

            // Determine if we have metadata/snapshots to store
            $hasMetadata = ! empty($this->pendingTransitionData);
            $hasSnapshot = true; // Always capture snapshots for audit trail

            // Create transition history record
            // For Code-First: workflow_id, transition_id will be null
            // For Database-First: all fields will be populated
            $historyRecord = WorkflowStateTransition::create([
                'transitionable_type' => static::class,
                'transitionable_id' => $this->getKey(),
                'workflow_id' => $workflowId,
                'transition_id' => $transitionId,
                'from_state' => $fromStateClass,
                'to_state' => $toStateClass,
                'from_state_label' => $fromStateLabel,
                'to_state_label' => $toStateLabel,
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'user_email' => $user?->email,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'notes' => $notes,
                'has_metadata' => $hasMetadata,
                'has_snapshot' => $hasSnapshot,
            ]);

            // Store transition metadata (form data, field changes)
            if ($hasMetadata) {
                WorkflowTransitionMetadata::create([
                    'transition_history_id' => $historyRecord->id,
                    'form_data' => $this->pendingTransitionData,
                ]);
            }

            // Store record snapshots (before and after)
            if ($hasSnapshot) {
                if ($this->preTransitionSnapshot) {
                    WorkflowTransitionSnapshot::create([
                        'transition_history_id' => $historyRecord->id,
                        'snapshot_type' => 'before',
                        'record_data' => $this->preTransitionSnapshot,
                    ]);
                }

                WorkflowTransitionSnapshot::create([
                    'transition_history_id' => $historyRecord->id,
                    'snapshot_type' => 'after',
                    'record_data' => $this->getAttributes(),
                ]);
            }

            // Clear pending transition data after logging
            $this->clearPendingTransitionData();
        } catch (Exception $e) {
            // Log but don't fail if logging fails
            report($e);
        }
    }

    /**
     * Extract transition notes from various sources.
     *
     * Priority order:
     * 1. Transition class getHistoryNotes() method (highest priority)
     * 2. Form field named by config (default: 'transition_notes')
     */
    protected function extractTransitionNotes(): ?string
    {
        // Check if logging notes is enabled
        if (! config('filament-flow.log_transition_notes', true)) {
            return null;
        }

        // Priority 1: Check if transition instance has getHistoryNotes() method
        if ($this->pendingTransitionInstance !== null) {
            if (method_exists($this->pendingTransitionInstance, 'getHistoryNotes')) {
                $notes = $this->pendingTransitionInstance->getHistoryNotes();
                if ($notes !== null && $notes !== '') {
                    return $notes;
                }
            }
        }

        // Priority 2: Check for configured field in transition data
        if ($this->pendingTransitionData !== null) {
            $notesField = config('filament-flow.transition_notes_field', 'transition_notes');

            if ($notesField !== null && isset($this->pendingTransitionData[$notesField])) {
                $notes = $this->pendingTransitionData[$notesField];
                if ($notes !== null && $notes !== '') {
                    return $notes;
                }
            }
        }

        return null;
    }
}
