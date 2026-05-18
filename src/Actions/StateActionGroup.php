<?php

namespace RoBYCoNTe\FilamentFlow\Actions;

use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\HasName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Services\ConditionEvaluator;
use RoBYCoNTe\FilamentFlow\Services\StateService;
use Spatie\ModelStates\State;

class StateActionGroup extends ActionGroup
{
    use HasName;

    public State|string|null $stateClass = null;

    public function __construct(?string $name, State|string|null $stateClass = null)
    {
        parent::__construct([]);
        $this->name($name);
        $this->stateClass($stateClass);
        $this->actions($this->generateStateTransitionActions($stateClass, $name));
    }

    public static function generate(string $columnName, State|string|null $stateClass, ?Model $record = null): static
    {
        if ($record) {
            $actions = static::forRecord($record, $columnName, $stateClass);
            $static = app(static::class, [
                'name' => $columnName,
                'stateClass' => $stateClass,
            ]);
            $static->actions($actions);
            $static->configure();

            return $static;
        }
        $static = app(static::class, [
            'name' => $columnName,
            'stateClass' => $stateClass,
        ]);
        $static->configure();

        return $static;
    }

    /**
     * Generate actions for a database-first workflow (no Spatie state class needed).
     *
     * Returns separate arrays for transitions and actions, rendered with a divider.
     *
     * @param  Model  $record  The record to generate actions for
     * @param  string  $columnName  The state column name
     * @return array Array of StateAction instances
     */
    public static function forDatabaseRecord(Model $record, string $columnName = 'state'): array
    {
        $actions = [];

        if (! config('filament-flow.enabled', true)) {
            return $actions;
        }

        try {
            $workflow = Workflow::findForModel(get_class($record), $columnName);

            if (! $workflow) {
                return $actions;
            }

            $currentState = $record->{$columnName};

            if ($currentState === null) {
                return $actions;
            }

            $currentStateKey = is_string($currentState) ? $currentState : get_class($currentState);

            $currentWorkflowState = WorkflowState::where('workflow_id', $workflow->id)
                ->where(function ($query) use ($currentStateKey) {
                    $query->where('class_name', $currentStateKey)
                        ->orWhere('name', $currentStateKey);
                })
                ->first();

            $conditionEvaluator = app(ConditionEvaluator::class);

            // Get transitions (to_state_id is NOT null)
            $transitions = WorkflowTransition::where('workflow_id', $workflow->id)
                ->whereNotNull('to_state_id')
                ->where(function ($query) use ($currentWorkflowState) {
                    $query->whereNull('from_state_id');
                    if ($currentWorkflowState) {
                        $query->orWhere('from_state_id', $currentWorkflowState->id);
                    }
                })
                ->with(['toState', 'fromState'])
                ->get();

            foreach ($transitions as $transition) {
                if (! $conditionEvaluator->evaluate($record, $transition->conditions)) {
                    continue;
                }

                $toState = $transition->toState;
                if (! $toState) {
                    continue;
                }

                $toStateIdentifier = $toState->class_name ?: $toState->name;
                $actionName = Str::slug('transition-'.$transition->name);
                $actionLabel = $transition->label ?: $toState->label;

                $action = StateAction::make($actionName)
                    ->label($actionLabel)
                    ->icon($toState->icon)
                    ->color($toState->color ?: 'primary')
                    ->attribute($columnName)
                    ->transitionTo($toStateIdentifier)
                    ->withTransitionClass($transition->class_name);

                $actions[] = $action;
            }

            // Get actions (to_state_id is null = self-transitions)
            $selfTransitions = WorkflowTransition::where('workflow_id', $workflow->id)
                ->whereNull('to_state_id')
                ->where(function ($query) use ($currentWorkflowState) {
                    $query->whereNull('from_state_id');
                    if ($currentWorkflowState) {
                        $query->orWhere('from_state_id', $currentWorkflowState->id);
                    }
                })
                ->get();

            if ($selfTransitions->isNotEmpty()) {
                // Add divider between transitions and actions
                if (! empty($actions)) {
                    $actions[] = Action::make('divider')
                        ->label('—')
                        ->disabled()
                        ->extraAttributes(['class' => 'opacity-30']);
                }

                foreach ($selfTransitions as $transition) {
                    if (! $conditionEvaluator->evaluate($record, $transition->conditions)) {
                        continue;
                    }

                    $actionName = Str::slug('action-'.$transition->name);

                    $action = StateAction::make($actionName)
                        ->label($transition->label ?: $transition->name)
                        ->icon($transition->metadata['icon'] ?? null)
                        ->color($transition->metadata['color'] ?? 'gray')
                        ->attribute($columnName)
                        ->transitionTo($currentStateKey); // Same state

                    if ($transition->class_name) {
                        $action->withTransitionClass($transition->class_name);
                    }

                    $actions[] = $action;
                }
            }
        } catch (Exception $e) {
            report($e);
        }

        return $actions;
    }

    /**
     * Generate StateAction instances for a specific record in modal contexts.
     */
    public static function forRecord(Model $record, string $columnName, State|string|null $stateClass): array
    {
        $actions = [];

        if (! config('filament-flow.enabled', true)) {
            return $actions;
        }

        // If no stateClass provided, use database-first approach
        if ($stateClass === null) {
            return static::forDatabaseRecord($record, $columnName);
        }

        try {
            // Get current state of the record
            $currentState = $record->{$columnName};
            $currentStateKey = is_string($currentState)
                ? $currentState
                : get_class($currentState);

            // Get the namespace and find workflow (PHP filter avoids SQLite LIKE backslash issues)
            $stateClassNamespace = (new ReflectionClass($stateClass))->getNamespaceName();

            $workflow = Workflow::where('state_column', $columnName)
                ->where('is_active', true)
                ->with('states')
                ->get()
                ->first(fn ($w) => $w->states->contains(fn ($s) => str_starts_with($s->class_name ?? '', $stateClassNamespace.'\\')));

            if (! $workflow) {
                return $actions;
            }

            // Get the current state from workflow
            $currentWorkflowState = WorkflowState::where('workflow_id', $workflow->id)
                ->where(function ($query) use ($currentStateKey) {
                    $query->where('class_name', $currentStateKey)
                        ->orWhere('name', $currentStateKey);
                })
                ->first();

            if (! $currentWorkflowState) {
                return $actions;
            }

            $conditionEvaluator = app(ConditionEvaluator::class);

            // Get all transitions FROM the current state (including global)
            $transitions = WorkflowTransition::where('workflow_id', $workflow->id)
                ->where(function ($query) use ($currentWorkflowState) {
                    $query->where('from_state_id', $currentWorkflowState->id)
                        ->orWhereNull('from_state_id');
                })
                ->with(['toState', 'fromState'])
                ->get();

            foreach ($transitions as $transition) {
                // Skip actions (self-transitions) in this loop
                if ($transition->to_state_id === null) {
                    continue;
                }

                // Evaluate conditions
                if (! $conditionEvaluator->evaluate($record, $transition->conditions)) {
                    continue;
                }

                $toState = $transition->toState;

                if (! $toState) {
                    continue;
                }

                // Determine the target state identifier
                $toStateIdentifier = $toState->class_name ?: $toState->name;

                // Use transition name for unique action identifier
                $actionName = Str::slug('transition-'.$transition->name);

                // Use transition label if available, otherwise fallback to state label
                $actionLabel = $transition->label ?: $toState->label;

                // Create StateAction (automatically includes transition forms and validation)
                $action = StateAction::make($actionName)
                    ->label($actionLabel)
                    ->icon($toState->icon)
                    ->color($toState->color ?: 'primary')
                    ->attribute($columnName)
                    ->transitionTo($toStateIdentifier)
                    ->withTransitionClass($transition->class_name);

                $actions[] = $action;
            }

            // Self-transitions (actions)
            $selfTransitions = $transitions->whereNull('to_state_id');

            if ($selfTransitions->isNotEmpty() && ! empty($actions)) {
                $actions[] = Action::make('divider')
                    ->label('—')
                    ->disabled()
                    ->extraAttributes(['class' => 'opacity-30']);
            }

            foreach ($selfTransitions as $transition) {
                if (! $conditionEvaluator->evaluate($record, $transition->conditions)) {
                    continue;
                }

                $actionName = Str::slug('action-'.$transition->name);
                $toStateIdentifier = $currentWorkflowState->class_name ?: $currentWorkflowState->name;

                $action = StateAction::make($actionName)
                    ->label($transition->label ?: $transition->name)
                    ->icon($transition->metadata['icon'] ?? null)
                    ->color($transition->metadata['color'] ?? 'gray')
                    ->attribute($columnName)
                    ->transitionTo($toStateIdentifier);

                if ($transition->class_name) {
                    $action->withTransitionClass($transition->class_name);
                }

                $actions[] = $action;
            }
        } catch (Exception $e) {
            // If we can't determine transitions, return empty array
            report($e);
        }

        return $actions;
    }

    /**
     * Generate StateAction instances for all possible state transitions.
     *
     * @return array<StateAction>
     */
    protected function generateStateTransitionActions(State|string|null $stateClass, string $name): array
    {
        $actions = [];

        if ($stateClass === null) {
            // Database-first: generate an action for every possible target state
            // Visibility will be controlled at runtime by StateAction
            return $this->generateDatabaseFirstActions($name);
        }

        // Get PHP state classes from Spatie
        $phpStates = $stateClass::all();

        foreach ($phpStates as $phpStateClass) {
            $state = new $phpStateClass(null);

            $actions[] = StateAction::make(Str::slug($state::getMorphClass()))
                ->attribute($name)
                ->transitionTo($state);
        }

        // Get states from database (both with and without PHP classes)
        if (config('filament-flow.enabled', true)) {
            try {
                // Get the namespace of the state class to match against
                $stateClassNamespace = (new ReflectionClass($stateClass))->getNamespaceName();

                // Find workflow (PHP filter avoids SQLite LIKE backslash issues)
                $workflow = Workflow::where('state_column', $name)
                    ->where('is_active', true)
                    ->with('states')
                    ->get()
                    ->first(fn ($w) => $w->states->contains(fn ($s) => str_starts_with($s->class_name ?? '', $stateClassNamespace.'\\')));

                if ($workflow) {
                    $modelClass = $workflow->model_type;
                    $stateService = app(StateService::class);
                    $allStates = $stateService->getAllStatesForModel($modelClass, $name);

                    // Filter out states that have PHP classes (already added above)
                    $phpStateKeys = array_map(fn ($s) => (new $s(null))::getMorphClass(), iterator_to_array($phpStates));

                    foreach ($allStates as $stateKey => $stateLabel) {
                        // Skip if this state has a PHP class (already processed)
                        if (in_array($stateKey, $phpStateKeys)) {
                            continue;
                        }

                        // This is a state defined only in database (no PHP class)
                        $actions[] = StateAction::make(Str::slug($stateKey))
                            ->attribute($name)
                            ->transitionTo($stateKey); // Pass string for database states without PHP classes
                    }
                }
            } catch (Exception $e) {
                report($e);
            }
        }

        return $actions;
    }

    /**
     * Generate actions for database-first workflows (no Spatie state class).
     * Creates one StateAction per transition defined in the workflow.
     * Visibility is controlled at runtime by StateAction::visible().
     *
     * @return array<StateAction>
     */
    protected function generateDatabaseFirstActions(string $columnName): array
    {
        $actions = [];

        if (! config('filament-flow.enabled', true)) {
            return $actions;
        }

        try {
            // We don't have the model class here (no record), so we find all workflows
            // with this state_column and generate actions for all their transitions.
            // The StateAction visibility logic will filter at runtime.
            // When multiple workflows exist (e.g., global + per-tenant with identical
            // definitions), we deduplicate by action name to avoid duplicate menu entries.
            $workflows = Workflow::where('state_column', $columnName)
                ->where('is_active', true)
                ->with(['transitions.toState', 'transitions.fromState'])
                ->get();

            $seenActionNames = [];

            foreach ($workflows as $workflow) {
                foreach ($workflow->transitions as $transition) {
                    $toState = $transition->toState;

                    if ($transition->to_state_id === null) {
                        // Self-transition / action
                        $actionName = Str::slug('action-'.$transition->name);
                    } elseif ($toState) {
                        // State transition
                        $actionName = Str::slug('transition-'.$transition->name);
                    } else {
                        continue;
                    }

                    // Skip duplicate action names (from overlapping workflows)
                    if (isset($seenActionNames[$actionName])) {
                        continue;
                    }
                    $seenActionNames[$actionName] = true;

                    if ($transition->to_state_id === null) {
                        $actions[] = StateAction::make($actionName)
                            ->label($transition->label ?: $transition->name)
                            ->icon($transition->metadata['icon'] ?? null)
                            ->color($transition->metadata['color'] ?? 'gray')
                            ->attribute($columnName);
                    } else {
                        $toStateIdentifier = $toState->class_name ?: $toState->name;
                        $actionLabel = $transition->label ?: $toState->label;

                        $actions[] = StateAction::make($actionName)
                            ->label($actionLabel)
                            ->icon($toState->icon)
                            ->color($toState->color ?: 'primary')
                            ->attribute($columnName)
                            ->transitionTo($toStateIdentifier)
                            ->withTransitionClass($transition->class_name);
                    }
                }
            }
        } catch (Exception $e) {
            report($e);
        }

        return $actions;
    }

    public function stateClass(State|string|null $stateClass): static
    {
        $this->stateClass = $stateClass;

        return $this;
    }

    /** @noinspection PhpUnused */
    public function getStateClass(): State|string|null
    {
        return $this->stateClass;
    }
}
