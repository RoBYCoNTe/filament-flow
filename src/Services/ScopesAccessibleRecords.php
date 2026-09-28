<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Support\AccessibleStates;
use RoBYCoNTe\FilamentFlow\Support\AccessibleStatesScope;
use RoBYCoNTe\FilamentFlow\Support\WorkflowCacheManager;
use Spatie\ModelStates\State;

/**
 * Turning "who may see what" into a query.
 *
 * A scope cannot be answered rule by rule: the states a user may reach are collected once
 * (and remembered while the request lasts), then the query is narrowed to them and to the
 * records a tenant may see.
 */
trait ScopesAccessibleRecords
{
    /**
     * Scope query to only include records accessible by user
     */
    /**
     * @param  int|null  $tenantId  the owner the workflow belongs to, when the host keeps one
     *                              workflow per owner (a workflow per scheme, for example):
     *                              without it no workflow is found and the query, instead of
     *                              being narrowed, comes back as it was.
     */
    public function scopeAccessible(Builder $query, ?Model $user = null, string $accessType = 'view', ?int $tenantId = null): Builder
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
        $workflow = Workflow::findForModel($modelClass, 'state', $tenantId);

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

        // The clauses live with the engine that also serves the hosts: the list of a call and
        // the rows this scope narrows are the same sentences, said once.
        return AccessibleStatesScope::apply(
            $query,
            AccessibleStates::of($categorized['free'], $categorized['assigned']),
            $user,
            $accessType,
            $stateColumn,
        );
    }

    /**
     * Categorize states into 'free' (accessible by role) and 'assigned' (only via @assigned
     * rule).
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
        $tenantId = method_exists($record, 'getWorkflowTenantId') ? $record->getWorkflowTenantId() : null;
        $workflow = Workflow::findForModel(get_class($record), 'state', $tenantId);

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
        $tenantId = method_exists($record, 'getWorkflowTenantId') ? $record->getWorkflowTenantId() : null;
        $workflow = Workflow::findForModel(get_class($record), 'state', $tenantId);

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
