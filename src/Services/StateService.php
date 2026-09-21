<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Exception;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Support\WorkflowCacheManager;

class StateService
{
    /**
     * Get all states for a model (PHP classes + database states)
     * Returns array of ['state_key' => 'State Label']
     */
    public function getAllStatesForModel(string $modelClass, string $stateColumn = 'state', ?int $tenantId = null): array
    {
        $states = [];

        if (config('filament-flow.enabled', true)) {
            $states = $this->getDatabaseStates($modelClass, $stateColumn, $tenantId);
        }

        try {
            $model = new $modelClass;
            $stateClass = $this->extractStateClass($model->getCasts()[$stateColumn] ?? null);

            if ($stateClass && method_exists($stateClass, 'getStatesLabel')) {
                $phpStates = $stateClass::getStatesLabel($modelClass);

                foreach ($phpStates as $key => $label) {
                    $states[$key] = $label;
                }
            }
        } catch (Exception $e) {
            report($e);
        }

        return $states;
    }

    /**
     * Get states defined in database
     */
    protected function getDatabaseStates(string $modelClass, string $stateColumn, ?int $tenantId = null): array
    {
        // Hosts may keep one workflow per owner (a workflow per scheme, for
        // example): without the tenant only the global workflow would be found.
        $workflow = Workflow::findForModel($modelClass, $stateColumn, $tenantId);

        if (! $workflow) {
            return [];
        }

        $cache = new WorkflowCacheManager;
        $cacheKey = "states:{$modelClass}:{$stateColumn}:{$tenantId}";

        $ttl = config('filament-flow.cache.safety_ttl', 86400);

        return $cache->remember($cacheKey, $ttl, function () use ($workflow) {
            // The order the workflow declares, with the name as a tiebreaker: the
            // list of states is rendered as it is, and a database is free to hand
            // the rows back in any order.
            $workflowStates = WorkflowState::where('workflow_id', $workflow->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $states = [];
            foreach ($workflowStates as $workflowState) {
                $className = $workflowState->class_name;

                if ($className === null || ! class_exists($className)) {
                    $states[$workflowState->name] = $workflowState->label;
                }
            }

            return $states;
        }, [$cache->stateTag($workflow->id)]);
    }

    /**
     * Get state metadata (color, icon, description) for a given state
     */
    public function getStateMetadata(string $modelClass, string $stateName, string $stateColumn = 'state', ?int $tenantId = null): ?array
    {
        $workflow = Workflow::findForModel($modelClass, $stateColumn, $tenantId);

        if (! $workflow) {
            return null;
        }

        $cache = new WorkflowCacheManager;
        $cacheKey = "state_meta:{$workflow->id}:{$stateName}";

        $ttl = config('filament-flow.cache.safety_ttl', 86400);

        return $cache->remember($cacheKey, $ttl, function () use ($workflow, $stateName) {
            $workflowState = WorkflowState::where('workflow_id', $workflow->id)
                ->where('name', $stateName)
                ->first();

            if (! $workflowState) {
                return null;
            }

            return [
                'label' => $workflowState->label,
                'color' => $workflowState->color,
                'icon' => $workflowState->icon,
                'description' => $workflowState->description,
                'is_initial' => $workflowState->is_initial,
                'is_final' => $workflowState->is_final,
                'sort_order' => $workflowState->sort_order,
            ];
        }, [$cache->stateTag($workflow->id)]);
    }

    /**
     * Get the initial state for a model from the workflow definition
     */
    public function getInitialState(string $modelClass, string $stateColumn = 'state'): ?string
    {
        $workflow = Workflow::findForModel($modelClass, $stateColumn);

        if (! $workflow) {
            return null;
        }

        $cache = new WorkflowCacheManager;
        $cacheKey = "initial_state:{$workflow->id}";

        $ttl = config('filament-flow.cache.safety_ttl', 86400);

        return $cache->remember($cacheKey, $ttl, function () use ($workflow) {
            $initialState = WorkflowState::where('workflow_id', $workflow->id)
                ->where('is_initial', true)
                ->first();

            return $initialState?->name;
        }, [$cache->stateTag($workflow->id)]);
    }

    /**
     * Extract the base state class from cast definition
     * Handles both Spatie's default cast and FlexibleStateCast
     */
    protected function extractStateClass(?string $cast): ?string
    {
        if (! $cast) {
            return null;
        }

        if (str_contains($cast, 'FlexibleStateCast:')) {
            $parts = explode(':', $cast);

            return $parts[1] ?? null;
        }

        return $cast;
    }
}
