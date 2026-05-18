<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use RoBYCoNTe\FilamentFlow\Contracts\PermissionResolver;
use RoBYCoNTe\FilamentFlow\Contracts\RoleResolver;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;

/**
 * Evaluates access rules against a user and record
 *
 * This class handles the core logic of determining whether a user
 * has access based on various rule types (roles, assignments, permissions, etc.)
 */
class AccessRuleEvaluator
{
    protected RoleResolver $roleResolver;

    protected PermissionResolver $permissionResolver;

    public function __construct(
        ?RoleResolver $roleResolver = null,
        ?PermissionResolver $permissionResolver = null
    ) {
        $this->roleResolver = $roleResolver ?? $this->resolveRoleResolver();
        $this->permissionResolver = $permissionResolver ?? $this->resolvePermissionResolver();
    }

    /**
     * Evaluate a single rule against a user and record
     */
    public function evaluateRule(string $rule, Model $user, Model $record): bool
    {
        // Everyone has access
        if ($rule === WorkflowStateAccessRule::RULE_ALL) {
            return true;
        }

        // Must be authenticated (and they are since we have a user)
        if ($rule === WorkflowStateAccessRule::RULE_AUTHENTICATED) {
            return true;
        }

        // Check ownership
        if ($rule === WorkflowStateAccessRule::RULE_OWNER) {
            return $this->evaluateOwnerRule($user, $record);
        }

        // Check assignment rules
        if (str_starts_with($rule, WorkflowStateAccessRule::RULE_ASSIGNED)) {
            return $this->evaluateAssignmentRule($rule, $user, $record);
        }

        // Check role rules
        if (str_starts_with($rule, WorkflowStateAccessRule::RULE_PREFIX_ROLE)) {
            return $this->evaluateRoleRule($rule, $user);
        }

        // Check permission rules
        if (str_starts_with($rule, WorkflowStateAccessRule::RULE_PREFIX_PERMISSION)) {
            return $this->evaluatePermissionRule($rule, $user, $record);
        }

        // Unknown rule type - deny by default
        Log::warning("FilamentFlow: Unknown access rule type '{$rule}' - denying access by default.");

        return false;
    }

    /**
     * Evaluate multiple rules with a specific operator
     *
     * @param  array<string>  $rules
     */
    public function evaluateRules(array $rules, string $operator, Model $user, Model $record): bool
    {
        if (empty($rules)) {
            return false;
        }

        if ($operator === WorkflowStateAccessRule::OPERATOR_AND) {
            // All rules must pass
            foreach ($rules as $rule) {
                if (! $this->evaluateRule($rule, $user, $record)) {
                    return false;
                }
            }

            return true;
        }

        // OR operator - any rule must pass
        foreach ($rules as $rule) {
            if ($this->evaluateRule($rule, $user, $record)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluate owner rule
     */
    protected function evaluateOwnerRule(Model $user, Model $record): bool
    {
        $ownerField = config('filament-flow.state_access.owner_field', 'user_id');

        if (! isset($record->{$ownerField})) {
            return false;
        }

        return $record->{$ownerField} === $user->getKey();
    }

    /**
     * Evaluate assignment rule
     */
    protected function evaluateAssignmentRule(string $rule, Model $user, Model $record): bool
    {
        // Check if record has assignment capability
        if (! method_exists($record, 'isAssignedTo')) {
            return false;
        }

        // Check for specific assignment type
        if ($rule !== WorkflowStateAccessRule::RULE_ASSIGNED) {
            // Extract assignment type from @assigned:type
            $parts = explode(':', $rule, 2);
            $assignmentType = $parts[1] ?? null;

            if ($assignmentType) {
                /** @noinspection PhpUndefinedMethodInspection */
                return $record->hasAssignmentType($user, $assignmentType);
            }
        }

        // Any assignment type
        return $record->isAssignedTo($user);
    }

    /**
     * Evaluate role rule
     */
    protected function evaluateRoleRule(string $rule, Model $user): bool
    {
        $roleString = substr($rule, strlen(WorkflowStateAccessRule::RULE_PREFIX_ROLE));
        $roles = array_map('trim', explode(',', $roleString));

        return $this->roleResolver->hasAnyRole($user, $roles);
    }

    /**
     * Evaluate permission rule
     */
    protected function evaluatePermissionRule(string $rule, Model $user, Model $record): bool
    {
        $permission = substr($rule, strlen(WorkflowStateAccessRule::RULE_PREFIX_PERMISSION));

        return $this->permissionResolver->hasPermission($user, $permission, $record);
    }

    /**
     * Check if user is a super admin (bypasses all checks)
     */
    public function isSuperAdmin(Model $user): bool
    {
        return $this->roleResolver->isSuperAdmin($user);
    }

    /**
     * Get the role resolver instance
     */
    public function getRoleResolver(): RoleResolver
    {
        return $this->roleResolver;
    }

    /**
     * Get the permission resolver instance
     */
    public function getPermissionResolver(): PermissionResolver
    {
        return $this->permissionResolver;
    }

    /**
     * Parse a rule string into its components
     *
     * @return array{type: string, value: string|null}
     */
    public function parseRule(string $rule): array
    {
        if ($rule === WorkflowStateAccessRule::RULE_ALL) {
            return ['type' => 'all', 'value' => null];
        }

        if ($rule === WorkflowStateAccessRule::RULE_AUTHENTICATED) {
            return ['type' => 'authenticated', 'value' => null];
        }

        if ($rule === WorkflowStateAccessRule::RULE_OWNER) {
            return ['type' => 'owner', 'value' => null];
        }

        if (str_starts_with($rule, WorkflowStateAccessRule::RULE_ASSIGNED)) {
            $parts = explode(':', $rule, 2);

            return ['type' => 'assigned', 'value' => $parts[1] ?? null];
        }

        if (str_starts_with($rule, WorkflowStateAccessRule::RULE_PREFIX_ROLE)) {
            $value = substr($rule, strlen(WorkflowStateAccessRule::RULE_PREFIX_ROLE));

            return ['type' => 'role', 'value' => $value];
        }

        if (str_starts_with($rule, WorkflowStateAccessRule::RULE_PREFIX_PERMISSION)) {
            $value = substr($rule, strlen(WorkflowStateAccessRule::RULE_PREFIX_PERMISSION));

            return ['type' => 'permission', 'value' => $value];
        }

        return ['type' => 'unknown', 'value' => $rule];
    }

    /**
     * Resolve the role resolver from config or use default
     */
    protected function resolveRoleResolver(): RoleResolver
    {
        $resolverClass = config('filament-flow.state_access.role_resolver');

        if ($resolverClass && class_exists($resolverClass)) {
            return app($resolverClass);
        }

        return new DefaultRoleResolver;
    }

    /**
     * Resolve the permission resolver from config or use default
     */
    protected function resolvePermissionResolver(): PermissionResolver
    {
        $resolverClass = config('filament-flow.state_access.permission_resolver');

        if ($resolverClass && class_exists($resolverClass)) {
            return app($resolverClass);
        }

        return new DefaultPermissionResolver;
    }
}
