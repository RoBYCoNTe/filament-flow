<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\HasAccessRules;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Support\AccessRuleEvaluator;
use RoBYCoNTe\FilamentFlow\Support\WorkflowCacheManager;
use Spatie\ModelStates\State;

/**
 * Service for managing state-based access control
 *
 * This service provides methods to check if a user can view, edit, or
 * transition records based on their current state and configured access rules.
 */
class WorkflowStateAccessService
{
    protected AccessRuleEvaluator $evaluator;

    public function __construct(?AccessRuleEvaluator $evaluator = null)
    {
        $this->evaluator = $evaluator ?? new AccessRuleEvaluator;
    }

    /**
     * Check if access control is enabled
     */
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
        $workflow = Workflow::findForModel(get_class($record));

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

        $roles = array_map('trim', explode(',', $roleValue));

        // Try Spatie Permission first
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }

        // Fallback to role attribute
        $userRole = $user->getAttribute('role');

        return $userRole && in_array($userRole, $roles, true);
    }

    /**
     * Check if user can create a record of a given model class
     *
     * This checks the create access rules on the INITIAL state of the workflow.
     * If no workflow exists, falls back to config defaults.
     *
     * @param  string  $modelClass  The fully qualified class name of the model
     * @param  Model|null  $user  The user to check (defaults to authenticated user)
     */
    public function canCreate(string $modelClass, ?Model $user = null): bool
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
        $workflow = Workflow::findForModel($modelClass);

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

            // Check if the state class implements HasAccessRules (using is_subclass_of for static check)
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
                    $config = $baseStateClass::config();
                    if (method_exists($config, 'defaultStateClass')) {
                        $defaultStateClass = $config->defaultStateClass;

                        if ($defaultStateClass && class_exists($defaultStateClass)) {
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

    /**
     * Core access check method
     */
    protected function checkAccess(Model $record, ?Model $user, string $accessType): bool
    {
        // If access control is disabled, allow everything
        if (! $this->isEnabled()) {
            return true;
        }

        // No user means no access (unless public rules exist)
        if ($user === null) {
            $user = auth()->user();
        }

        // Get the current state
        $state = $this->getRecordState($record);

        if ($state === null) {
            // No state = use default rules
            return $this->checkDefaultRules($user, $accessType, $record);
        }

        // Super admin bypass
        if ($user && $this->evaluator->isSuperAdmin($user)) {
            return true;
        }

        // Assignment-level access override (short-circuit before state rules)
        if ($user && method_exists($record, 'hasAccessOverride') && $record->hasAccessOverride($user, $accessType)) {
            return true;
        }

        // Try Code-First rules first (PHP State class)
        if ($state instanceof State) {
            $result = $this->checkCodeFirstRules($state, $user, $accessType, $record);
            if ($result !== null) {
                return $result;
            }
        }

        // Try Database rules
        $result = $this->checkDatabaseRules($record, $user, $accessType, $state);
        if ($result !== null) {
            return $result;
        }

        // Fall back to default rules
        return $this->checkDefaultRules($user, $accessType, $record);
    }

    /**
     * Check Code-First access rules (defined in PHP State class)
     *
     * Supports two approaches:
     * 1. HasAccessRules interface (recommended): getCreateAccessRules(), getViewAccessRules(), getEditAccessRules(), getTransitionAccessRules()
     * 2. Legacy accessRules() method: returns array with 'create', 'view', 'edit', 'transition' keys
     */
    protected function checkCodeFirstRules(State $state, ?Model $user, string $accessType, Model $record): ?bool
    {
        $stateClass = get_class($state);

        // Method 1: Check if state implements HasAccessRules interface (recommended)
        if ($state instanceof HasAccessRules) {
            /** @var HasAccessRules $stateClass */
            $accessRules = match ($accessType) {
                WorkflowStateAccessRule::ACCESS_TYPE_CREATE => $stateClass::getCreateAccessRules(),
                WorkflowStateAccessRule::ACCESS_TYPE_VIEW => $stateClass::getViewAccessRules(),
                WorkflowStateAccessRule::ACCESS_TYPE_EDIT => $stateClass::getEditAccessRules(),
                WorkflowStateAccessRule::ACCESS_TYPE_TRANSITION => $stateClass::getTransitionAccessRules(),
                default => null,
            };

            if ($accessRules !== null) {
                return $this->evaluateCodeFirstRules($accessRules, $user, $record);
            }
        }

        // Method 2: Check legacy accessRules() method for backwards compatibility
        if (method_exists($stateClass, 'accessRules')) {
            $rules = $stateClass::accessRules();

            if (isset($rules[$accessType])) {
                return $this->evaluateCodeFirstRules($rules[$accessType], $user, $record);
            }
        }

        return null;
    }

    /**
     * Evaluate Code-First access rules
     */
    protected function evaluateCodeFirstRules(array $accessRules, ?Model $user, Model $record): bool
    {
        // Handle empty rules array
        if (empty($accessRules)) {
            return false;
        }

        // No user and no public rule
        if ($user === null) {
            return in_array('*', $accessRules, true);
        }

        // Evaluate rules (OR logic by default for Code-First)
        return $this->evaluator->evaluateRules($accessRules, WorkflowStateAccessRule::OPERATOR_OR, $user, $record);
    }

    /**
     * Check Database access rules
     */
    protected function checkDatabaseRules(Model $record, ?Model $user, string $accessType, $state): ?bool
    {
        // Find the workflow state in database
        $workflowState = $this->findWorkflowState($record, $state);

        if (! $workflowState) {
            return null;
        }

        $cache = new WorkflowCacheManager;
        $cacheKey = "access_rules:{$workflowState->id}:{$accessType}";
        $ttl = config('filament-flow.cache.safety_ttl', 86400);

        $rules = $cache->remember($cacheKey, $ttl, function () use ($workflowState, $accessType) {
            return WorkflowStateAccessRule::query()
                ->forState($workflowState->id)
                ->forAccessType($accessType)
                ->active()
                ->byPriority()
                ->get();
        }, [$cache->accessTag($workflowState->id)]);

        if ($rules->isEmpty()) {
            return null;
        }

        // No user
        if ($user === null) {
            // Check if any rule is public
            foreach ($rules as $rule) {
                if ($rule->isPublic()) {
                    return true;
                }
            }

            return false;
        }

        // Group rules by operator
        $orRules = $rules->where('operator', WorkflowStateAccessRule::OPERATOR_OR)->pluck('rule')->toArray();
        $andRules = $rules->where('operator', WorkflowStateAccessRule::OPERATOR_AND)->pluck('rule')->toArray();

        // Evaluate AND rules first (all must pass)
        if (! empty($andRules)) {
            if (! $this->evaluator->evaluateRules($andRules, WorkflowStateAccessRule::OPERATOR_AND, $user, $record)) {
                return false;
            }
        }

        // Evaluate OR rules (any must pass)
        if (! empty($orRules)) {
            return $this->evaluator->evaluateRules($orRules, WorkflowStateAccessRule::OPERATOR_OR, $user, $record);
        }

        // If only AND rules existed, and they passed, allow access
        if (! empty($andRules)) {
            return true;
        }

        return null;
    }

    /**
     * Check default access rules from config
     */
    protected function checkDefaultRules(?Model $user, string $accessType, ?Model $record): bool
    {
        $defaults = config('filament-flow.state_access.defaults', [
            'create' => ['@authenticated'],
            'view' => ['@authenticated'],
            'edit' => ['@authenticated'],
            'transition' => ['@authenticated'],
        ]);

        $rules = $defaults[$accessType] ?? ['@authenticated'];

        // No user
        if ($user === null) {
            return in_array('*', $rules, true);
        }

        // For create checks (no record yet), use evaluateCreateRules
        if ($record === null) {
            return $this->evaluateCreateRules($rules, $user);
        }

        return $this->evaluator->evaluateRules($rules, WorkflowStateAccessRule::OPERATOR_OR, $user, $record);
    }

    /**
     * Get access rules for a state
     *
     * @return array<string>
     */
    public function getAccessRules($state, string $accessType): array
    {
        $rules = [];

        // Try Code-First rules
        if ($state instanceof State) {
            $stateClass = get_class($state);

            // Method 1: HasAccessRules interface (recommended)
            if ($state instanceof HasAccessRules) {
                /** @var HasAccessRules $stateClass */
                $codeRules = match ($accessType) {
                    WorkflowStateAccessRule::ACCESS_TYPE_CREATE => $stateClass::getCreateAccessRules(),
                    WorkflowStateAccessRule::ACCESS_TYPE_VIEW => $stateClass::getViewAccessRules(),
                    WorkflowStateAccessRule::ACCESS_TYPE_EDIT => $stateClass::getEditAccessRules(),
                    WorkflowStateAccessRule::ACCESS_TYPE_TRANSITION => $stateClass::getTransitionAccessRules(),
                    default => [],
                };
                $rules = array_merge($rules, $codeRules);
            }
            // Method 2: Legacy accessRules() method
            elseif (method_exists($stateClass, 'accessRules')) {
                $codeRules = $stateClass::accessRules();
                if (isset($codeRules[$accessType])) {
                    $rules = array_merge($rules, $codeRules[$accessType]);
                }
            }
        }

        // Try to find Database rules
        $stateValue = $state instanceof State ? get_class($state) : $state;
        $workflowState = WorkflowState::where('class_name', $stateValue)
            ->orWhere('name', $stateValue)
            ->first();

        if ($workflowState) {
            /** @noinspection PhpUndefinedMethodInspection */
            $dbRules = WorkflowStateAccessRule::query()
                ->forState($workflowState->id)
                ->forAccessType($accessType)
                ->active()
                ->pluck('rule')
                ->toArray();

            $rules = array_merge($rules, $dbRules);
        }

        return array_unique($rules);
    }

    /**
     * Scope query to only include records accessible by user
     */
    public function scopeAccessible(Builder $query, ?Model $user = null, string $accessType = 'view'): Builder
    {
        if (! $this->isEnabled()) {
            return $query;
        }

        if ($user === null) {
            $user = auth()->user();
        }

        // Super admin sees everything
        if ($user && $this->evaluator->isSuperAdmin($user)) {
            return $query;
        }

        // Get model class
        $modelClass = $query->getModel()::class;

        // Find active workflow for this model (with tenant fallback support)
        $workflow = Workflow::findForModel($modelClass);

        if (! $workflow) {
            // No workflow = use default rules (allow authenticated)
            if ($user === null) {
                // No user and default is @authenticated = no results
                $defaults = config('filament-flow.state_access.defaults', []);
                $rules = $defaults[$accessType] ?? ['@authenticated'];
                if (! in_array('*', $rules, true)) {
                    $query->whereRaw('1 = 0'); // No results
                }
            }

            return $query;
        }

        // Get state column
        $stateColumn = $workflow->state_column ?? 'state';

        // Get states categorized by access type
        $categorized = $this->categorizeAccessibleStates($workflow, $user, $accessType);

        $hasOverrideSupport = $user && method_exists($query->getModel(), 'assignments');

        if (empty($categorized['free']) && empty($categorized['assigned']) && ! $hasOverrideSupport) {
            // No accessible states and no override support = no results
            $query->whereRaw('1 = 0');

            return $query;
        }

        // Build query: free states OR (assigned states AND user is assigned) OR (has access override)
        $query->where(function (Builder $q) use ($stateColumn, $categorized, $user, $accessType, $hasOverrideSupport) {
            $hasCondition = false;

            if (! empty($categorized['free'])) {
                $q->whereIn($stateColumn, $categorized['free']);
                $hasCondition = true;
            }

            if (! empty($categorized['assigned']) && $user) {
                $q->orWhere(function (Builder $sub) use ($stateColumn, $categorized, $user) {
                    $sub->whereIn($stateColumn, $categorized['assigned']);

                    if (method_exists($sub->getModel(), 'assignments')) {
                        $sub->whereHas('assignments', function (Builder $aq) use ($user) {
                            $aq->where('user_id', $user->getKey());
                        });
                    }
                });
                $hasCondition = true;
            }

            // Access override: user has an assignment with override for this access type
            if ($hasOverrideSupport) {
                $overrideColumn = 'override_'.$accessType;
                $q->orWhereHas('assignments', function (Builder $aq) use ($user, $overrideColumn) {
                    $aq->where('user_id', $user->getKey())
                        ->where($overrideColumn, true);
                });
                $hasCondition = true;
            }

            if (! $hasCondition) {
                $q->whereRaw('1 = 0');
            }
        });

        return $query;
    }

    /**
     * Categorize states into 'free' (accessible by role) and 'assigned' (only via @assigned rule).
     *
     * @return array{free: array<string>, assigned: array<string>}
     */
    protected function categorizeAccessibleStates(Workflow $workflow, ?Model $user, string $accessType): array
    {
        if (config('filament-flow.cache.enabled', true) && $user) {
            $cache = new WorkflowCacheManager;
            $cacheKey = "access_cat:{$workflow->id}:{$user->getKey()}:{$accessType}";
            $ttl = min(config('filament-flow.cache.safety_ttl', 86400), 3600);

            return $cache->remember($cacheKey, $ttl, function () use ($workflow, $user, $accessType) {
                return $this->categorizeAccessibleStatesUncached($workflow, $user, $accessType);
            }, [$cache->accessTag($workflow->id)]);
        }

        return $this->categorizeAccessibleStatesUncached($workflow, $user, $accessType);
    }

    /**
     * Uncached categorization of accessible states.
     *
     * @return array{free: array<string>, assigned: array<string>}
     */
    protected function categorizeAccessibleStatesUncached(Workflow $workflow, ?Model $user, string $accessType): array
    {
        $freeStates = [];
        $assignedStates = [];

        $states = $workflow->states()->get();
        $stateIds = $states->pluck('id')->toArray();

        /** @noinspection PhpUndefinedMethodInspection */
        $allRules = WorkflowStateAccessRule::whereIn('state_id', $stateIds)
            ->where('access_type', $accessType)
            ->where('is_active', true)
            ->get()
            ->groupBy('state_id');

        foreach ($states as $state) {
            $rules = ($allRules[$state->id] ?? collect())->pluck('rule')->toArray();

            if (empty($rules)) {
                $defaults = config('filament-flow.state_access.defaults', []);
                $rules = $defaults[$accessType] ?? ['@authenticated'];
            }

            $hasFreeAccess = false;
            $hasAssignedAccess = false;

            if ($user === null) {
                $hasFreeAccess = in_array('*', $rules, true);
            } else {
                foreach ($rules as $rule) {
                    if ($rule === '*' ||
                        $rule === '@authenticated' ||
                        (str_starts_with($rule, 'role:') && $this->evaluator->evaluateRule($rule, $user, $workflow))) {
                        $hasFreeAccess = true;
                        break;
                    }
                }

                if (! $hasFreeAccess) {
                    foreach ($rules as $rule) {
                        if (str_starts_with($rule, '@assigned') || $rule === '@owner') {
                            $hasAssignedAccess = true;
                            break;
                        }
                    }
                }
            }

            $stateNames = [];
            if ($state->class_name) {
                $stateNames[] = $state->class_name;
            }
            /** @noinspection PhpPossiblePolymorphicInvocationInspection */
            $stateNames[] = $state->name;

            if ($hasFreeAccess) {
                $freeStates = array_merge($freeStates, $stateNames);
            } elseif ($hasAssignedAccess) {
                $assignedStates = array_merge($assignedStates, $stateNames);
            }
        }

        return [
            'free' => array_unique($freeStates),
            'assigned' => array_unique($assignedStates),
        ];
    }

    /**
     * Get list of states accessible by user
     *
     * @return array<string>
     */
    protected function getAccessibleStates(Workflow $workflow, ?Model $user, string $accessType): array
    {
        if (config('filament-flow.cache.enabled', true) && $user) {
            $cache = new WorkflowCacheManager;
            $cacheKey = "access_states:{$workflow->id}:{$user->getKey()}:{$accessType}";
            $ttl = min(config('filament-flow.cache.safety_ttl', 86400), 3600);

            return $cache->remember($cacheKey, $ttl, function () use ($workflow, $user, $accessType) {
                return $this->getAccessibleStatesUncached($workflow, $user, $accessType);
            }, [$cache->accessTag($workflow->id)]);
        }

        return $this->getAccessibleStatesUncached($workflow, $user, $accessType);
    }

    /**
     * Uncached version of getAccessibleStates.
     *
     * @return array<string>
     */
    protected function getAccessibleStatesUncached(Workflow $workflow, ?Model $user, string $accessType): array
    {
        $categorized = $this->categorizeAccessibleStatesUncached($workflow, $user, $accessType);

        return array_unique(array_merge($categorized['free'], $categorized['assigned']));
    }

    /**
     * Get the current state of a record
     */
    protected function getRecordState(Model $record): mixed
    {
        // Try common state field names
        $stateFields = ['state', 'status', 'workflow_state'];

        foreach ($stateFields as $field) {
            if (isset($record->{$field})) {
                return $record->{$field};
            }
        }

        // Check if there's a workflow for this model (with tenant fallback support)
        $workflow = Workflow::findForModel(get_class($record));

        if ($workflow && $workflow->state_column) {
            return $record->{$workflow->state_column} ?? null;
        }

        return null;
    }

    /**
     * Find the workflow state in database
     */
    protected function findWorkflowState(Model $record, $state): ?WorkflowState
    {
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        $workflow = Workflow::findForModel(get_class($record));

        if (! $workflow) {
            return null;
        }

        $stateValue = $state instanceof State ? get_class($state) : (string) $state;

        $cache = new WorkflowCacheManager;
        $cacheKey = "wf_state:{$workflow->id}:{$stateValue}";
        $ttl = config('filament-flow.cache.safety_ttl', 86400);

        return $cache->remember($cacheKey, $ttl, function () use ($workflow, $stateValue) {
            return $workflow->states()
                ->where(function ($query) use ($stateValue) {
                    $query->where('class_name', $stateValue)
                        ->orWhere('name', $stateValue);
                })
                ->first();
        }, [$cache->stateTag($workflow->id)]);
    }
}
