<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\HasAccessRules;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Support\WorkflowCacheManager;
use Spatie\ModelStates\State;

/**
 * Reading the access rules of a state, and deciding what they mean.
 *
 * The rules come from two places — the state classes of the host (code-first) and the rows
 * in the database — and both are asked the same question: given this record and this actor,
 * is this action allowed? Code-first wins when it answers, otherwise the database decides,
 * and the rules of the workflow itself decide last.
 */
trait EvaluatesAccessRules
{
    /**
     * Core access check method
     */
    public function checkAccess(Model $record, ?Model $user, string $accessType): bool
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
     * Supports two approaches: 1. HasAccessRules interface (recommended):
     * getCreateAccessRules(), getViewAccessRules(), getEditAccessRules(),
     * getTransitionAccessRules() 2. Legacy accessRules() method: returns array with 'create',
     * 'view', 'edit', 'transition' keys
     */
    protected function checkCodeFirstRules(State $state, ?Model $user, string $accessType, Model $record): ?bool
    {
        $stateClass = get_class($state);

        // Method 1: Check if state implements HasAccessRules interface (recommended)
        if ($state instanceof HasAccessRules) {
            $accessRules = match ($accessType) {
                WorkflowStateAccessRule::ACCESS_TYPE_CREATE => $state::getCreateAccessRules(),
                WorkflowStateAccessRule::ACCESS_TYPE_VIEW => $state::getViewAccessRules(),
                WorkflowStateAccessRule::ACCESS_TYPE_EDIT => $state::getEditAccessRules(),
                WorkflowStateAccessRule::ACCESS_TYPE_TRANSITION => $state::getTransitionAccessRules(),
                default => null,
            };

            if ($accessRules !== null) {
                return $this->evaluateCodeFirstRules($accessRules, $user, $record);
            }
        }

        // Method 2: Check legacy accessRules() method for backwards compatibility
        if (method_exists($state, 'accessRules')) {
            $class = $state::class;
            $rules = $class::accessRules();

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
                $codeRules = match ($accessType) {
                    WorkflowStateAccessRule::ACCESS_TYPE_CREATE => $state::getCreateAccessRules(),
                    WorkflowStateAccessRule::ACCESS_TYPE_VIEW => $state::getViewAccessRules(),
                    WorkflowStateAccessRule::ACCESS_TYPE_EDIT => $state::getEditAccessRules(),
                    WorkflowStateAccessRule::ACCESS_TYPE_TRANSITION => $state::getTransitionAccessRules(),
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
}
