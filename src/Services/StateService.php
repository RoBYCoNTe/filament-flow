<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Exception;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;

class StateService
{
    /**
     * Get all states for a model (PHP classes + database states)
     * Returns array of ['state_key' => 'State Label']
     */
    public function getAllStatesForModel(string $modelClass, string $stateColumn = 'state'): array
    {
        $states = [];

        // Get states from database first
        if (config('filament-flow.enabled', true)) {
            $states = $this->getDatabaseStates($modelClass, $stateColumn);
        }

        // Get states from PHP classes (Spatie) and merge
        // PHP states with same key override database states for metadata consistency
        try {
            $model = new $modelClass;
            $stateClass = $this->extractStateClass($model->getCasts()[$stateColumn] ?? null);

            if ($stateClass && method_exists($stateClass, 'getStatesLabel')) {
                $phpStates = $stateClass::getStatesLabel($modelClass);

                // Merge: PHP states take precedence (better metadata)
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
     * Only returns states that DON'T have a PHP class (class_name is null)
     * States with PHP classes will be retrieved via Spatie
     */
    protected function getDatabaseStates(string $modelClass, string $stateColumn): array
    {
        // Get workflow with tenant fallback support
        $workflow = Workflow::findForModel($modelClass, $stateColumn);

        if (! $workflow) {
            return [];
        }

        // Get all workflow states and filter to database-only states
        // A state is "database-only" if it has no class_name or its class_name is not a valid PHP class
        $workflowStates = WorkflowState::where('workflow_id', $workflow->id)->get();

        $states = [];
        foreach ($workflowStates as $workflowState) {
            $className = $workflowState->class_name;

            // Include state if: no class_name, or class_name is not a real PHP class
            if ($className === null || ! class_exists($className)) {
                $states[$workflowState->name] = $workflowState->label;
            }
        }

        return $states;
    }

    /**
     * Get state metadata (color, icon, description) for a given state
     */
    public function getStateMetadata(string $modelClass, string $stateName, string $stateColumn = 'state'): ?array
    {
        // Get workflow with tenant fallback support
        $workflow = Workflow::findForModel($modelClass, $stateColumn);

        if (! $workflow) {
            return null;
        }

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

        $initialState = WorkflowState::where('workflow_id', $workflow->id)
            ->where('is_initial', true)
            ->first();

        return $initialState?->name;
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

        // If using FlexibleStateCast, extract the state class from the parameter
        // Format: "RoBYCoNTe\FilamentFlow\Casts\FlexibleStateCast:App\States\Order\OrderState"
        if (str_contains($cast, 'FlexibleStateCast:')) {
            $parts = explode(':', $cast);

            return $parts[1] ?? null;
        }

        return $cast;
    }
}
