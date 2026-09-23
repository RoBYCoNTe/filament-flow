<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Support\WorkflowStateMemoryCache;
use Spatie\ModelStates\State;
use Throwable;

/**
 * Where a transition comes from and where it goes.
 *
 * The states a model walks are written twice: as classes in the host application and as
 * rows in the database. This is the trait that joins them — it turns a state class into its
 * row, a row into its class, finds the transition that links two of them, and keeps the
 * instance the host declared around while the transition runs.
 */
trait ResolvesWorkflowStates
{
    /**
     * Return the tenant ID used to scope the workflow lookup to this specific record.
     * Override in your model to enable per-record workflow scoping.
     * Returning null falls back to the global Filament tenant (getCurrentTenantId()).
     */
    public function getWorkflowTenantId(): ?int
    {
        return null;
    }

    /**
     * Set the user performing the transition (for access control)
     */
    public function asUser(?Model $user): static
    {
        $this->transitionUser = $user;

        return $this;
    }

    /**
     * Get the base State class for a given field, handling FlexibleStateCast format.
     */
    protected function getBaseStateClass(string $field = 'state'): ?string
    {
        $casts = $this->getCasts();
        $cast = $casts[$field] ?? null;

        if (! $cast) {
            return null;
        }

        // Handle FlexibleStateCast format: "FlexibleStateCast:BaseState"
        if (str_contains($cast, ':')) {
            $parts = explode(':', $cast, 2);
            $castClass = $parts[0];
            $stateClass = $parts[1] ?? null;

            // If the first part is FlexibleStateCast, return the second part
            if ($stateClass && str_contains($castClass, 'FlexibleStateCast')) {
                return $stateClass;
            }
        }

        // If the cast itself is a State class, return it directly
        if (class_exists($cast) && is_subclass_of($cast, State::class)) {
            return $cast;
        }

        return $cast;
    }

    /**
     * Helper method to get workflow state by class name or name.
     * Uses an in-memory cache to avoid repeated queries within the same request.
     */
    private function getWorkflowState(Workflow $workflow, string $stateClass): ?WorkflowState
    {
        $workflowId = $workflow->getAttribute('id');

        if (WorkflowStateMemoryCache::has($workflowId, $stateClass)) {
            return WorkflowStateMemoryCache::get($workflowId, $stateClass);
        }

        $state = WorkflowState::where('workflow_id', $workflowId)
            ->where(function ($query) use ($stateClass) {
                $query->where('class_name', $stateClass)
                    ->orWhere('name', $stateClass);
            })
            ->first();

        WorkflowStateMemoryCache::set($workflowId, $stateClass, $state);

        return $state;
    }

    /**
     * Find a transition configuration record for from → to state.
     * Handles nullable from_state_id (global transitions) by preferring specific over global.
     */
    protected function findTransitionConfig(Workflow $workflow, ?WorkflowState $fromWorkflowState, ?WorkflowState $toWorkflowState): ?WorkflowTransition
    {
        $fromStateId = $fromWorkflowState?->getAttribute('id');
        $toStateId = $toWorkflowState?->getAttribute('id');

        // First try specific transition (exact from → to)
        if ($fromStateId && $toStateId) {
            $transition = WorkflowTransition::where('workflow_id', $workflow->id)
                ->where('from_state_id', $fromStateId)
                ->where('to_state_id', $toStateId)
                ->first();

            if ($transition) {
                return $transition;
            }
        }

        // Fallback: global transition (from_state_id null → to)
        if ($toStateId) {
            return WorkflowTransition::where('workflow_id', $workflow->id)
                ->whereNull('from_state_id')
                ->where('to_state_id', $toStateId)
                ->first();
        }

        return null;
    }

    /**
     * Auto-detect and prepare transition instance for notes extraction.
     * Uses Spatie's StateConfig to find the registered transition class.
     *
     * @param  State|string|null  $fromState  Current state
     * @param  State|string  $toState  Target state
     */
    protected function autoDetectTransitionInstance(State|string|null $fromState, State|string $toState): void
    {
        // Skip if notes logging is disabled
        if (! config('filament-flow.log_transition_notes', true)) {
            return;
        }

        // Skip if we don't have a valid from state
        if ($fromState === null) {
            return;
        }

        try {
            // Get the state class names
            $fromStateClass = is_string($fromState) ? $fromState : get_class($fromState);
            $toStateClass = is_string($toState) ? $toState : get_class($toState);

            // Get the base state class from casts (handles FlexibleStateCast format)
            $baseStateClass = $this->getBaseStateClass('state');

            if (! $baseStateClass || ! class_exists($baseStateClass)) {
                return;
            }

            // Get StateConfig and resolve transition class
            if (method_exists($baseStateClass, 'config')) {
                $stateConfig = $baseStateClass::config();

                if (method_exists($stateConfig, 'resolveTransitionClass')) {
                    $transitionClass = $stateConfig->resolveTransitionClass($fromStateClass, $toStateClass);

                    if ($transitionClass && class_exists($transitionClass)) {
                        // Instantiate the transition with model and data
                        $this->pendingTransitionInstance = new $transitionClass($this, $this->pendingTransitionData);
                    }
                }
            }
        } catch (Throwable) {
            // Silently fail - notes are optional
        }
    }

    /**
     * Set the transition instance for notes extraction.
     * Can be called manually if needed, but auto-detection is preferred.
     *
     * @deprecated Use autoDetectTransitionInstance instead. This method is kept for backwards
     * compatibility.
     */
    public function setTransitionInstance(object $transition): void
    {
        $this->pendingTransitionInstance = $transition;
    }

    /**
     * Clear pending transition data after logging.
     */
    protected function clearPendingTransitionData(): void
    {
        $this->pendingTransitionData = null;
        $this->pendingTransitionInstance = null;
        $this->preTransitionSnapshot = null;
        $this->transitionUser = null;
    }
}
