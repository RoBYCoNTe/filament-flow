<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotification as WorkflowNotificationConfig;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use Spatie\ModelStates\State;

/**
 * Finding what should be notified.
 *
 * Notifications are declared on three different things — a transition, the entry into a
 * state, the exit from it — and the rows that carry them are gathered here, together with
 * the workflow and the state they belong to.
 */
trait FindsNotificationTargets
{
    /**
     * Get the workflow for a model (with tenant fallback support).
     */
    protected function getWorkflowForModel(Model $record, string $stateColumn = 'state'): ?Workflow
    {
        $tenantId = method_exists($record, 'getWorkflowTenantId') ? $record->getWorkflowTenantId() : null;

        return Workflow::findForModel(get_class($record), $stateColumn, $tenantId);
    }

    /**
     * Build localized label entries for a transition context.
     *
     * Looks up state and transition labels stored in the workflow database
     * configuration so that notification templates can use human-readable,
     * translated values via {{from_state_label}}, {{to_state_label}}, and
     * {{transition_label}} instead of raw state codes.
     *
     * @return array{from_state_label: string, to_state_label: string, transition_label: string}
     */
    private function buildTransitionContextLabels(Workflow $workflow, string $fromState, string $toState): array
    {
        $fromWs = $this->resolveWorkflowState($workflow, $fromState);
        $toWs = $this->resolveWorkflowState($workflow, $toState);

        $transition = null;

        if ($fromWs && $toWs) {
            $transition = $workflow->transitions()
                ->where('from_state_id', $fromWs->id)
                ->where('to_state_id', $toWs->id)
                ->first();
        } elseif ($fromWs) {
            $transition = $workflow->transitions()
                ->where('from_state_id', $fromWs->id)
                ->first();
        }

        return [
            'from_state_label' => $fromWs->label ?? Str::headline($fromState),
            'to_state_label' => $toWs->label ?? Str::headline($toState),
            'transition_label' => $transition->label ?? '',
        ];
    }

    /**
     * Resolve a workflow state by class_name or name.
     */
    protected function resolveWorkflowState(Workflow $workflow, string $state): ?WorkflowState
    {
        return $workflow->states()
            ->where(function ($q) use ($state) {
                $q->where('class_name', $state)
                    ->orWhere('name', $state);
            })
            ->first();
    }

    /**
     * Find notifications configured for a specific transition.
     */
    protected function findTransitionNotifications(
        Workflow $workflow,
        string $fromState,
        string $toState
    ): Collection {
        // Resolve from-state to ID first, then find transition
        $fromWs = $this->resolveWorkflowState($workflow, $fromState);

        if (! $fromWs) {
            // No from-state found — return all on_transition notifications as fallback
            return WorkflowNotificationConfig::where('workflow_id', $workflow->id)
                ->where('trigger_event', 'on_transition')
                ->where('is_active', true)
                ->get();
        }

        $transition = $workflow->transitions()
            ->where('from_state_id', $fromWs->id)
            ->first();

        if (! $transition) {
            return WorkflowNotificationConfig::where('workflow_id', $workflow->id)
                ->where('trigger_event', 'on_transition')
                ->where('is_active', true)
                ->get();
        }

        return WorkflowNotificationConfig::where('workflow_id', $workflow->id)
            ->where('trigger_event', 'on_transition')
            ->where('transition_id', $transition->id)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Find notifications for state entry.
     */
    protected function findStateEntryNotifications(Workflow $workflow, string $state): Collection
    {
        $workflowState = $this->resolveWorkflowState($workflow, $state);

        if (! $workflowState) {
            return collect();
        }

        return WorkflowNotificationConfig::where('workflow_id', $workflow->id)
            ->where('trigger_event', 'on_state_enter')
            ->where('state_id', $workflowState->id)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Find notifications for state exit.
     */
    protected function findStateExitNotifications(Workflow $workflow, string $state): Collection
    {
        $workflowState = $this->resolveWorkflowState($workflow, $state);

        if (! $workflowState) {
            return collect();
        }

        return WorkflowNotificationConfig::where('workflow_id', $workflow->id)
            ->where('trigger_event', 'on_state_exit')
            ->where('state_id', $workflowState->id)
            ->where('is_active', true)
            ->get();
    }
}
