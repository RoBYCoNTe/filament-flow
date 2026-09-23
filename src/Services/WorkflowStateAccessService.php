<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\HasAccessRules;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Support\AccessibleStates;
use RoBYCoNTe\FilamentFlow\Support\AccessRuleEvaluator;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Service for managing state-based access control
 *
 * This service provides methods to check if a user can view, edit, or
 * transition records based on their current state and configured access rules.
 */
class WorkflowStateAccessService
{
    use EvaluatesAccessRules;
    use ScopesAccessibleRecords;

    protected AccessRuleEvaluator $evaluator;

    public function __construct(?AccessRuleEvaluator $evaluator = null)
    {
        $this->evaluator = $evaluator ?? new AccessRuleEvaluator;
    }

    /**
     * The states of a model that a user may see, split by **how** they get there — opened by
     * their role, or as the owner or an assignee — inside an outcome that also says what it
     * means when there are none: the administrator is not filtered, whoever has no workflow
     * sees nothing.
     */
    public function categorizedAccessibleStates(
        string $modelClass,
        ?Model $user = null,
        string $accessType = 'view',
        ?int $tenantId = null,
    ): AccessibleStates {
        $user ??= auth()->user();

        if ($user === null || ! $this->isEnabled()) {
            return AccessibleStates::none();
        }

        if ($this->evaluator->isSuperAdmin($user)) {
            return AccessibleStates::unrestricted();
        }

        $workflow = Workflow::findForModel($modelClass, 'state', $tenantId);

        if ($workflow === null) {
            return AccessibleStates::none();
        }

        $categorized = $this->categorizeAccessibleStates($workflow, $user, $accessType);

        return AccessibleStates::of($categorized['free'], $categorized['assigned']);
    }

    public function isEnabled(): bool
    {
        return config('filament-flow.state_access.enabled', true);
    }

    /**
     * Check if user can view a record
     */
    public function canView(Model $record, ?Model $user = null): bool
    {
        return $this->checkAccess($record, $user, WorkflowStateAccessRule::ACCESS_TYPE_VIEW);
    }

    /**
     * Check if user can edit a record
     */
    public function canEdit(Model $record, ?Model $user = null): bool
    {
        return $this->checkAccess($record, $user, WorkflowStateAccessRule::ACCESS_TYPE_EDIT);
    }

    /**
     * Check if user can transition a record to another state
     */
    public function canTransition(Model $record, ?Model $user = null, ?string $toState = null): bool
    {
        // First check state-level access rules
        $stateAccess = $this->checkAccess($record, $user, WorkflowStateAccessRule::ACCESS_TYPE_TRANSITION);

        if (! $stateAccess || $toState === null) {
            return $stateAccess;
        }

        // When $toState is provided, also check transition-specific permissions
        return $this->checkTransitionPermissions($record, $user, $toState);
    }

    /**
     * Check transition-specific permissions defined on WorkflowTransitionPermission
     */
    protected function checkTransitionPermissions(Model $record, ?Model $user, string $toState): bool
    {
        $tenantId = method_exists($record, 'getWorkflowTenantId') ? $record->getWorkflowTenantId() : null;
        $workflow = Workflow::findForModel(get_class($record), 'state', $tenantId);

        if (! $workflow) {
            return true;
        }

        $state = $this->getRecordState($record);

        if ($state === null) {
            return true;
        }

        $stateValue = $state instanceof State ? get_class($state) : (string) $state;

        // Find workflow states for current and target
        $fromWorkflowState = $workflow->states()
            ->where(fn ($q) => $q->where('class_name', $stateValue)->orWhere('name', $stateValue))
            ->first();

        $toWorkflowState = $workflow->states()
            ->where(fn ($q) => $q->where('class_name', $toState)->orWhere('name', $toState))
            ->first();

        if (! $fromWorkflowState || ! $toWorkflowState) {
            return true;
        }

        // Find the specific transition
        $transition = WorkflowTransition::where('workflow_id', $workflow->id)
            ->where('from_state_id', $fromWorkflowState->id)
            ->where('to_state_id', $toWorkflowState->id)
            ->first();

        if (! $transition) {
            return true;
        }

        $permissions = $transition->permissions()->get();

        if ($permissions->isEmpty()) {
            return true;
        }

        if (! $user) {
            return false;
        }

        $requireAll = $permissions->contains('require_all', true);

        foreach ($permissions as $permission) {
            $passed = match ($permission->permission_type) {
                'role' => $this->checkRolePermission($user, $permission->permission_value),
                'assignment' => method_exists($record, 'isAssignedTo') && $record->isAssignedTo($user),
                default => false,
            };

            if ($requireAll && ! $passed) {
                return false;
            }

            if (! $requireAll && $passed) {
                return true;
            }
        }

        return $requireAll;
    }

    /**
     * Check if user has a specific role for transition permission
     */
    protected function checkRolePermission(Model $user, ?string $roleValue): bool
    {
        if (! $roleValue) {
            return false;
        }

        $required = array_map('trim', explode(',', $roleValue));

        return array_intersect($required, $this->resolveUserRoles($user)) !== [];
    }

    /**
     * Roles of a user, resolved the same way as the field permissions: the
     * configured resolver first (tenant aware roles live there), then Spatie
     * Permission and finally a plain `role` attribute.
     *
     * @return array<int,string>
     */
    protected function resolveUserRoles(Model $user): array
    {
        // One place decides what a role is: the resolver configured for the package.
        // The transition permissions of the model and the field permissions ask the same
        // question through the same seam, so a host that keeps roles outside the user
        // model — a custom resolver, a tenant role, a super admin of its own — is no
        // longer treated differently here than it is there.
        return array_values(array_map('strval', $this->evaluator->getRoleResolver()->getRoles($user)));
    }

    /**
     * Whether a user may create a record of a given model: the create access rules on the
     * **initial** state of the workflow, or the configured default when no workflow exists.
     *
     * @param  string  $modelClass  the model to create (used to resolve the workflow)
     * @param  Model|null  $user  the user to check (defaults to the authenticated user)
     * @param  int|null  $tenantId  the owner of the scoped workflow (the scheme of a call);
     *                              when omitted, the global/current-tenant workflow is used
     */
    public function canCreate(string $modelClass, ?Model $user = null, ?int $tenantId = null): bool
    {
        // If access control is disabled, allow everything
        if (! $this->isEnabled()) {
            return true;
        }

        if ($user === null) {
            $user = auth()->user();
        }

        // Super admin bypass
        if ($user && $this->evaluator->isSuperAdmin($user)) {
            return true;
        }

        // Find the workflow for this model (with tenant fallback support)
        $workflow = Workflow::findForModel($modelClass, 'state', $tenantId);

        if (! $workflow) {
            // No workflow = check if there's a default initial state class
            return $this->checkCreateWithoutWorkflow($modelClass, $user);
        }

        // Find the initial state
        $initialState = $workflow->states()
            ->where('is_initial', true)
            ->first();

        if (! $initialState) {
            // No initial state defined, use default rules
            return $this->checkDefaultRules($user, WorkflowStateAccessRule::ACCESS_TYPE_CREATE, null);
        }

        // Check Code-First rules if state has a PHP class
        if ($initialState->class_name && class_exists($initialState->class_name)) {
            $stateClass = $initialState->class_name;

            // Check if the state class implements HasAccessRules (using is_subclass_of for
            // static check)
            if (is_subclass_of($stateClass, HasAccessRules::class)) {
                $accessRules = $stateClass::getCreateAccessRules();
                if (! empty($accessRules)) {
                    return $this->evaluateCreateRules($accessRules, $user);
                }
            }
        }

        // Check Database rules
        /** @noinspection PhpUndefinedMethodInspection */
        $rules = WorkflowStateAccessRule::query()
            ->forState($initialState->id)
            ->forAccessType(WorkflowStateAccessRule::ACCESS_TYPE_CREATE)
            ->active()
            ->pluck('rule')
            ->toArray();

        if (! empty($rules)) {
            return $this->evaluateCreateRules($rules, $user);
        }

        // Fall back to default rules
        return $this->checkDefaultRules($user, WorkflowStateAccessRule::ACCESS_TYPE_CREATE, null);
    }

    /**
     * Check create access when no workflow exists (pure Code-First)
     */
    protected function checkCreateWithoutWorkflow(string $modelClass, ?Model $user): bool
    {
        // Try to find the initial state class from model casts
        try {
            $tempModel = new $modelClass;
            $casts = $tempModel->getCasts();
            $stateField = 'state';

            if (isset($casts[$stateField])) {
                $baseStateClass = $casts[$stateField];

                // Handle FlexibleStateCast format (ClassName:BaseClass)
                if (str_contains($baseStateClass, ':')) {
                    $parts = explode(':', $baseStateClass);
                    $baseStateClass = $parts[1] ?? $parts[0];
                }

                if (class_exists($baseStateClass) && method_exists($baseStateClass, 'config')) {
                    /** @var StateConfig $config */
                    $config = $baseStateClass::config();
                    $defaultStateClass = $config->defaultStateClass;

                    if ($defaultStateClass !== null) {
                        if (class_exists($defaultStateClass)) {
                            // Check if the state class implements HasAccessRules
                            if (is_subclass_of($defaultStateClass, HasAccessRules::class)) {
                                $accessRules = $defaultStateClass::getCreateAccessRules();
                                if (! empty($accessRules)) {
                                    return $this->evaluateCreateRules($accessRules, $user);
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Ignore errors
        }

        // Fall back to default rules
        return $this->checkDefaultRules($user, WorkflowStateAccessRule::ACCESS_TYPE_CREATE, null);
    }

    /**
     * Evaluate create access rules (doesn't need a record since it doesn't exist yet)
     */
    protected function evaluateCreateRules(array $rules, ?Model $user): bool
    {
        if (empty($rules)) {
            return false;
        }

        // No user
        if ($user === null) {
            return in_array('*', $rules, true);
        }

        // For create rules, we can only evaluate user-based rules (not @owner or @assigned)
        foreach ($rules as $rule) {
            // Public access
            if ($rule === '*') {
                return true;
            }

            // Authenticated user
            if ($rule === '@authenticated') {
                return true;
            }

            // Role-based
            if (str_starts_with($rule, 'role:')) {
                $roleString = substr($rule, 5);
                $roles = array_map('trim', explode(',', $roleString));
                if ($this->evaluator->getRoleResolver()->hasAnyRole($user, $roles)) {
                    return true;
                }
            }

            // Permission-based
            if (str_starts_with($rule, 'permission:')) {
                $permission = substr($rule, 11);
                if ($this->evaluator->getPermissionResolver()->hasPermission($user, $permission)) {
                    return true;
                }
            }
        }

        return false;
    }
}
