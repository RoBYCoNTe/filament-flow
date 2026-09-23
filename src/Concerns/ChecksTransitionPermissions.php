<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use RoBYCoNTe\FilamentFlow\Exceptions\FormulaConditionFailedException;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Services\ConditionEvaluator;
use RoBYCoNTe\FilamentFlow\Support\AccessRuleEvaluator;
use Spatie\ModelStates\State;

/**
 * Who is allowed to walk a transition.
 *
 * It is the half of the transition machinery that answers a question rather than doing
 * something: given a state, a target and a user, is this move permitted? The checks look at
 * the roles a transition declares (and its per-state overrides) and at the actor of the
 * moment, and they are asked both before acting and while drawing the available actions.
 */
trait ChecksTransitionPermissions
{
    /**
     * Check if a transition is allowed to a specific state
     * This extends Spatie's canTransitionTo to also check database-configured transitions
     *
     * Formula conditions that fail are silenced to false (they propagate as exceptions only
     * during execution).
     */
    public function canTransitionTo(string|State $state, string $field = 'state'): bool
    {
        $currentState = $this->{$field};

        // If current state is a string (database-only), use database check only
        if (is_string($currentState)) {
            if (config('filament-flow.enabled', true)) {
                try {
                    return $this->canTransitionToFromDatabaseString($currentState, $state, $field);
                } catch (FormulaConditionFailedException) {
                    return false;
                }
            }

            return false;
        }

        if (! $currentState instanceof State) {
            return false;
        }

        // Check if target state is database-only (doesn't resolve to a class)
        if (is_string($state)) {
            $baseStateClass = $this->getBaseStateClass($field);

            if ($baseStateClass && class_exists($baseStateClass) && method_exists($baseStateClass, 'resolveStateClass')) {
                $resolvedClass = $baseStateClass::resolveStateClass($state);

                // If it's database-only (resolves to null), skip Spatie check
                if ($resolvedClass === null && config('filament-flow.enabled', true)) {
                    try {
                        return $this->canTransitionToFromDatabase($currentState, $state, $field);
                    } catch (FormulaConditionFailedException) {
                        return false;
                    }
                }
            }
        }

        // Try parent's canTransitionTo (from Spatie)
        try {
            if ($currentState->canTransitionTo($state)) {
                return true;
            }
        } catch (Exception $e) {
            // If Spatie check fails, continue to database check
            report($e);
        }

        // Check database-configured transitions
        if (config('filament-flow.enabled', true)) {
            try {
                return $this->canTransitionToFromDatabase($currentState, $state, $field);
            } catch (FormulaConditionFailedException) {
                return false;
            }
        }

        return false;
    }

    /**
     * Check if transition exists in database configuration
     */
    public function canTransitionToFromDatabase(State $fromState, string|State $toState, string $field): bool
    {
        $fromStateClass = get_class($fromState);
        $toStateClass = is_string($toState) ? $toState : get_class($toState);

        return $this->canTransitionInternal($fromStateClass, $toStateClass, $field);
    }

    /**
     * Check if transition exists in database configuration (from string state)
     */
    public function canTransitionToFromDatabaseString(string $fromState, string|State $toState, string $field): bool
    {
        $toStateClass = is_string($toState) ? $toState : get_class($toState);

        return $this->canTransitionInternal($fromState, $toStateClass, $field);
    }

    /**
     * Internal method to check if transition is allowed (eliminates duplication)
     */
    private function canTransitionInternal(string $fromStateClass, string $toStateClass, string $field): bool
    {
        // Get workflow for this model (with tenant fallback support)
        $workflow = Workflow::findForModel(static::class, $field, $this->getWorkflowTenantId());

        if (! $workflow) {
            return false;
        }

        // Get workflow states
        $fromWorkflowState = $this->getWorkflowState($workflow, $fromStateClass);
        $toWorkflowState = $this->getWorkflowState($workflow, $toStateClass);

        if (! $toWorkflowState) {
            return false;
        }

        // Find transition (supports global transitions with null from_state_id)
        $transition = $this->findTransitionConfig($workflow, $fromWorkflowState, $toWorkflowState);

        if (! $transition) {
            return false;
        }

        // Check transition-level permissions
        if (! $this->checkTransitionPermissions($transition)) {
            return false;
        }

        // Evaluate conditions against the model
        return app(ConditionEvaluator::class)->evaluate($this, $transition->conditions);
    }

    /**
     * Check if the current user has permission to execute a specific transition.
     *
     * If no permissions are defined on the transition, it's allowed by default.
     * Permission types: 'role' (user must have role), 'assignment' (user must be assigned),
     * 'custom' (evaluated via metadata callback).
     */
    protected function checkTransitionPermissions(WorkflowTransition $transition): bool
    {
        $permissions = $transition->permissions()->get();

        if ($permissions->isEmpty()) {
            return true; // No permissions defined = allowed
        }

        $user = $this->transitionUser ?? Auth::user();

        if (! $user) {
            return false; // No user = denied when permissions exist
        }

        // If require_all is set on any permission, ALL must pass; otherwise ANY must pass
        $requireAll = $permissions->contains('require_all', true);

        foreach ($permissions as $permission) {
            $passed = match ($permission->permission_type) {
                'role' => $this->checkRolePermission($user, $permission->permission_value),
                'assignment' => method_exists($this, 'isAssignedTo') && $this->isAssignedTo($user),
                default => false,
            };

            if ($requireAll && ! $passed) {
                return false;
            }

            if (! $requireAll && $passed) {
                return true;
            }
        }

        return $requireAll; // If require_all and all passed, true; if OR and none passed, false
    }

    /**
     * Check if user has a specific role for transition permission.
     */
    private function checkRolePermission(Model $user, ?string $roleValue): bool
    {
        if (! $roleValue) {
            return false;
        }

        // Support comma-separated roles. The check goes through the configured
        // role resolver, so transition permissions see the same roles as the
        // state access rules and the field permissions.
        $roles = array_map('trim', explode(',', $roleValue));

        return app(AccessRuleEvaluator::class)->getRoleResolver()->hasAnyRole($user, $roles);
    }
}
