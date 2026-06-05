<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use DB;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use RoBYCoNTe\FilamentFlow\Events\StateEntered;
use RoBYCoNTe\FilamentFlow\Events\StateExited;
use RoBYCoNTe\FilamentFlow\Events\TransitionCompleted;
use RoBYCoNTe\FilamentFlow\Exceptions\ActionNotFoundException;
use RoBYCoNTe\FilamentFlow\Exceptions\ConditionNotMetException;
use RoBYCoNTe\FilamentFlow\Exceptions\InvalidStateException;
use RoBYCoNTe\FilamentFlow\Exceptions\UnauthorizedTransitionException;
use RoBYCoNTe\FilamentFlow\Exceptions\WorkflowNotFoundException;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionMetadata;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionSnapshot;
use RoBYCoNTe\FilamentFlow\Services\ConditionEvaluator;
use RoBYCoNTe\FilamentFlow\Services\NotificationService;
use RoBYCoNTe\FilamentFlow\Services\SideEffectExecutor;
use RoBYCoNTe\FilamentFlow\Services\TransitionFormService;
use RoBYCoNTe\FilamentFlow\Support\WorkflowStateMemoryCache;
use Spatie\ModelStates\Exceptions\TransitionNotFound;
use Spatie\ModelStates\State;
use Throwable;

trait HasDatabaseTransitions
{
    /**
     * Boot the trait: automatically set the initial workflow state on creation.
     */
    public static function bootHasDatabaseTransitions(): void
    {
        static::creating(function (Model $model) {
            if (! config('filament-flow.enabled', true)) {
                return;
            }

            // Determine the state column (default: 'state')
            $stateColumn = method_exists($model, 'getStateColumn')
                ? $model->getStateColumn()
                : 'state';

            // Only set if state is null (not already set by factory/seeder)
            if ($model->{$stateColumn} !== null) {
                return;
            }

            $workflow = Workflow::findForModel(static::class, $stateColumn);

            if (! $workflow) {
                return;
            }

            $initialState = $workflow->initialState();

            if ($initialState) {
                $model->{$stateColumn} = $initialState->class_name ?: $initialState->name;
            }
        });
    }

    /**
     * Temporary storage for transition data (used for logging notes)
     */
    protected ?array $pendingTransitionData = null;

    /**
     * Temporary storage for transition class instance (used for getting notes)
     */
    protected ?object $pendingTransitionInstance = null;

    /**
     * The user performing the transition (for access control)
     */
    protected ?Model $transitionUser = null;

    /**
     * Snapshot of record state before transition (for audit trail)
     */
    protected ?array $preTransitionSnapshot = null;

    /**
     * Set the user performing the transition (for access control)
     */
    public function asUser(?Model $user): static
    {
        $this->transitionUser = $user;

        return $this;
    }

    /**
     * Override transitionTo to handle database transitions
     *
     * @param  string|State  $state  Target state
     * @param  mixed  ...$arguments  Transition data (first argument should be arrayed of form data)
     *
     * @throws Exception
     * @throws Throwable
     * @throws UnauthorizedTransitionException If access control enforcement is enabled and user is not authorized
     */
    public function transitionTo(string|State $state, ...$arguments): static
    {
        // Capture pre-transition snapshot for audit trail
        try {
            $this->preTransitionSnapshot = $this->getAttributes();
        } catch (Throwable) {
            $this->preTransitionSnapshot = null;
        }

        // Check access control enforcement
        $this->enforceTransitionAccess($state);
        $field = 'state'; // Default field, could be made dynamic
        $currentState = $this->{$field};

        // Store transition data for logging (will be used in logTransition)
        if (! empty($arguments) && is_array($arguments[0] ?? null)) {
            $this->pendingTransitionData = $arguments[0];
        }

        // Auto-detect and prepare transition instance for notes extraction
        $this->autoDetectTransitionInstance($currentState, $state);

        if (! $currentState instanceof State) {
            // If current state is a string (database-only), handle it directly
            if (is_string($currentState) && config('filament-flow.enabled', true)) {
                return $this->executeDatabaseTransitionFromString($currentState, $state, $field, $arguments);
            }
            throw new InvalidStateException;
        }

        // Check if target state is database-only (string that doesn't resolve to a class)
        $resolvedClass = null;
        if (is_string($state)) {
            // Simple check: if it's a string that doesn't exist as a class, it's database-only
            if (! class_exists($state)) {
                // It's a database-only state
                if (config('filament-flow.enabled', true)) {
                    return $this->executeDatabaseTransition($currentState, $state, $field, $arguments);
                }
            }

            // Get the base state class (handles FlexibleStateCast format)
            $baseStateClass = $this->getBaseStateClass($field);

            if ($baseStateClass && class_exists($baseStateClass) && method_exists($baseStateClass, 'resolveStateClass')) {
                try {
                    $resolvedClass = $baseStateClass::resolveStateClass($state);
                } catch (Throwable) {
                }
            }
        }

        // If state is a string and doesn't resolve to a class (database-only state)
        // skip Spatie's transition and go directly to database transition
        if (is_string($state) && $resolvedClass === null && config('filament-flow.enabled', true)) {
            return $this->executeDatabaseTransition($currentState, $state, $field, $arguments);
        }

        // HYBRID APPROACH: Check if transition exists in database before trying Spatie
        // This allows mixing Code-First (Spatie) and Database-First (database configured) transitions
        if (config('filament-flow.enabled', true) && $this->canTransitionToFromDatabase($currentState, $state, $field)) {
            return $this->executeDatabaseTransition($currentState, $state, $field, $arguments);
        }

        // Try Spatie's normal transition first
        try {
            $previousState = $currentState;
            $currentState->transitionTo($state, ...$arguments);

            // Log the Spatie transition
            $this->logTransition($previousState, $state, $field);

            // Trigger notifications for Spatie transition
            $this->triggerTransitionNotifications($previousState, $state);

            // Dispatch lifecycle events
            $fromStateClass = get_class($previousState);
            $toStateClass = is_string($state) ? $state : get_class($state);
            $eventUser = $this->transitionUser ?? Auth::user();

            StateExited::dispatch($this, $fromStateClass, $eventUser);
            StateEntered::dispatch($this, $toStateClass, $eventUser);
            TransitionCompleted::dispatch($this, $fromStateClass, $toStateClass, $eventUser, $this->pendingTransitionData ?? []);

            return $this;
        } catch (Throwable $e) {
            // If Spatie transition not found, try database transition as fallback
            if (config('filament-flow.enabled', true)) {
                try {
                    return $this->executeDatabaseTransition($currentState, $state, $field, $arguments);
                } catch (Throwable) {
                    // If database transition also fails, re-throw the original Spatie exception
                    throw $e;
                }
            }

            // Re-throw if database transitions not enabled
            throw $e;
        }
    }

    /**
     * Execute a database-configured transition when current state is a string (database-only)
     *
     * @throws TransitionNotFound
     * @throws Exception
     */
    protected function executeDatabaseTransitionFromString(string $fromState, string|State $toState, string $field, array $arguments): static
    {
        $toStateClass = is_string($toState) ? $toState : get_class($toState);

        // Check if transition is allowed
        if (! $this->canTransitionToFromDatabaseString($fromState, $toState, $field)) {
            throw TransitionNotFound::make(
                $fromState,
                $toStateClass,
                static::class
            );
        }

        // Apply transition data first (before changing state)
        if (! empty($arguments) && is_array($arguments[0] ?? null)) {
            $this->applyTransitionDataFromString($fromState, $toState, $arguments[0], $field);
            // Save the model to persist transition data
            $this->save();
        }

        // Execute the transition
        return $this->executeTheTransition($toState, $field, $toStateClass, $fromState);
    }

    /**
     * Execute a database-configured transition
     *
     * @throws TransitionNotFound
     * @throws Exception
     */
    protected function executeDatabaseTransition(State $fromState, string|State $toState, string $field, array $arguments): static
    {
        $toStateClass = is_string($toState) ? $toState : get_class($toState);

        // Check if transition is allowed
        if (! $this->canTransitionToFromDatabase($fromState, $toState, $field)) {
            throw TransitionNotFound::make(
                get_class($fromState),
                $toStateClass,
                static::class
            );
        }

        // Apply transition data first (before changing state)
        if (! empty($arguments) && is_array($arguments[0] ?? null)) {
            $this->applyTransitionData($fromState, $toState, $arguments[0], $field);
            // Save the model to persist transition data
            $this->save();
        }

        // Execute the transition
        return $this->executeTheTransition($toState, $field, $toStateClass, $fromState);
    }

    /**
     * Apply transition data to model
     *
     * @throws Exception
     */
    protected function applyTransitionData(State $fromState, string|State $toState, array $data, string $field): void
    {
        $fromStateClass = get_class($fromState);
        $toStateClass = is_string($toState) ? $toState : get_class($toState);
        $this->applyTransitionDataInternal($fromStateClass, $toStateClass, $data, $field);
    }

    /**
     * Apply transition data to model (from string state)
     *
     * @throws Exception
     */
    protected function applyTransitionDataFromString(string $fromState, string|State $toState, array $data, string $field): void
    {
        $toStateClass = is_string($toState) ? $toState : get_class($toState);
        $this->applyTransitionDataInternal($fromState, $toStateClass, $data, $field);
    }

    /**
     * Internal method to apply transition data (eliminates duplication)
     *
     * @throws Exception
     */
    private function applyTransitionDataInternal(string $fromStateClass, string $toStateClass, array $data, string $field): void
    {
        // Get transition configuration (with tenant fallback support)
        $workflow = Workflow::findForModel(static::class, $field);

        if (! $workflow) {
            return;
        }

        $fromWorkflowState = $this->getWorkflowState($workflow, $fromStateClass);
        $toWorkflowState = $this->getWorkflowState($workflow, $toStateClass);

        // Find transition (supports global transitions with null from_state_id)
        $transition = $this->findTransitionConfig($workflow, $fromWorkflowState, $toWorkflowState);

        if (! $transition) {
            return;
        }

        // Eager load fields for TransitionFormService
        $transition->load('fields');

        // Use TransitionFormService to apply data
        $service = app(TransitionFormService::class);
        $service->applyTransitionDataToModel($this, $transition, $data);
    }

    /**
     * Check if a transition is allowed to a specific state
     * This extends Spatie's canTransitionTo to also check database-configured transitions
     */
    public function canTransitionTo(string|State $state, string $field = 'state'): bool
    {
        $currentState = $this->{$field};

        // If current state is a string (database-only), use database check only
        if (is_string($currentState)) {
            if (config('filament-flow.enabled', true)) {
                return $this->canTransitionToFromDatabaseString($currentState, $state, $field);
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
                    return $this->canTransitionToFromDatabase($currentState, $state, $field);
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
            return $this->canTransitionToFromDatabase($currentState, $state, $field);
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
     * Execute an in-state action (self-transition) by transition name.
     *
     * Actions are transitions with to_state_id = null — they don't change state
     * but still log history, execute side effects, and trigger notifications.
     *
     * @param  string  $transitionName  The name of the action/transition
     * @param  array  $data  Form data to apply
     * @param  string  $field  The state column
     *
     * @throws Exception
     * @throws Throwable
     */
    public function executeAction(string $transitionName, array $data = [], string $field = 'state'): static
    {
        $currentState = $this->{$field};
        $currentStateClass = is_string($currentState) ? $currentState : get_class($currentState);

        // Capture pre-transition snapshot for audit trail
        try {
            $this->preTransitionSnapshot = $this->getAttributes();
        } catch (Throwable) {
            $this->preTransitionSnapshot = null;
        }

        // Store transition data for logging
        if (! empty($data)) {
            $this->pendingTransitionData = $data;
        }

        $workflow = Workflow::findForModel(static::class, $field);

        if (! $workflow) {
            throw new WorkflowNotFoundException(static::class);
        }

        $fromWorkflowState = $this->getWorkflowState($workflow, $currentStateClass);

        // Find action: to_state_id is null, from_state_id matches current or is null (global)
        $transition = WorkflowTransition::where('workflow_id', $workflow->id)
            ->where('name', $transitionName)
            ->whereNull('to_state_id')
            ->where(function ($query) use ($fromWorkflowState) {
                $query->whereNull('from_state_id');
                if ($fromWorkflowState) {
                    $query->orWhere('from_state_id', $fromWorkflowState->getAttribute('id'));
                }
            })
            ->first();

        if (! $transition) {
            throw new ActionNotFoundException($transitionName);
        }

        // Check permissions
        if (! $this->checkTransitionPermissions($transition)) {
            throw new UnauthorizedTransitionException($this, $currentStateClass, $currentStateClass, $this->transitionUser ?? Auth::user());
        }

        // Check conditions
        if (! app(ConditionEvaluator::class)->evaluate($this, $transition->conditions)) {
            throw new ConditionNotMetException($transitionName);
        }

        // Apply transition data if fields are configured
        if (! empty($data) && $transition->fields()->exists()) {
            $service = app(TransitionFormService::class);
            $service->applyTransitionDataToModel($this, $transition, $data);
            $this->save();
        }

        // Log the action (from and to are the same state)
        $this->logTransition($currentStateClass, $currentStateClass, $field, $transition);

        // Execute side effects
        app(SideEffectExecutor::class)->execute($this, $transition);

        // Trigger notifications
        $this->triggerTransitionNotifications($currentStateClass, $currentStateClass);

        // Dispatch events
        $eventUser = $this->transitionUser ?? Auth::user();
        TransitionCompleted::dispatch($this, $currentStateClass, $currentStateClass, $eventUser, $this->pendingTransitionData ?? []);

        // Clear pending data
        $this->clearPendingTransitionData();

        return $this;
    }

    /**
     * Get all transitions available from the current state (state-changing only).
     *
     * @return Collection<WorkflowTransition>
     */
    public function getAvailableTransitions(string $field = 'state'): Collection
    {
        $currentState = $this->{$field};
        $currentStateClass = is_string($currentState) ? $currentState : get_class($currentState);

        $workflow = Workflow::findForModel(static::class, $field);

        if (! $workflow) {
            return collect();
        }

        $fromWorkflowState = $this->getWorkflowState($workflow, $currentStateClass);

        // Find transitions: to_state_id is NOT null, from_state_id matches or is null (global)
        return WorkflowTransition::where('workflow_id', $workflow->id)
            ->whereNotNull('to_state_id')
            ->where(function ($query) use ($fromWorkflowState) {
                $query->whereNull('from_state_id');
                if ($fromWorkflowState) {
                    $query->orWhere('from_state_id', $fromWorkflowState->getAttribute('id'));
                }
            })
            ->get()
            ->filter(function (WorkflowTransition $transition) {
                // Check permissions
                if (! $this->checkTransitionPermissions($transition)) {
                    return false;
                }

                // Check conditions
                return app(ConditionEvaluator::class)->evaluate($this, $transition->conditions);
            })
            ->values();
    }

    /**
     * Get all actions (self-transitions) available from the current state.
     *
     * @return Collection<WorkflowTransition>
     */
    public function getAvailableActions(string $field = 'state'): Collection
    {
        $currentState = $this->{$field};
        $currentStateClass = is_string($currentState) ? $currentState : get_class($currentState);

        $workflow = Workflow::findForModel(static::class, $field);

        if (! $workflow) {
            return collect();
        }

        $fromWorkflowState = $this->getWorkflowState($workflow, $currentStateClass);

        // Find actions: to_state_id is null, from_state_id matches or is null (global)
        return WorkflowTransition::where('workflow_id', $workflow->id)
            ->whereNull('to_state_id')
            ->where(function ($query) use ($fromWorkflowState) {
                $query->whereNull('from_state_id');
                if ($fromWorkflowState) {
                    $query->orWhere('from_state_id', $fromWorkflowState->getAttribute('id'));
                }
            })
            ->get()
            ->filter(function (WorkflowTransition $transition) {
                if (! $this->checkTransitionPermissions($transition)) {
                    return false;
                }

                return app(ConditionEvaluator::class)->evaluate($this, $transition->conditions);
            })
            ->values();
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
     * Internal method to check if transition is allowed (eliminates duplication)
     */
    private function canTransitionInternal(string $fromStateClass, string $toStateClass, string $field): bool
    {
        // Get workflow for this model (with tenant fallback support)
        $workflow = Workflow::findForModel(static::class, $field);

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

        // Support comma-separated roles
        $roles = array_map('trim', explode(',', $roleValue));

        // Try Spatie Permission method first
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }

        // Fallback: check 'role' attribute
        if (isset($user->role)) {
            return in_array($user->role, $roles, true);
        }

        return false;
    }

    /**
     * Log the transition to the workflow_state_transitions table
     * Supports both Database-First (with Workflow) and Code-First (without Workflow) approaches
     */
    protected function logTransition(State|string $fromState, State|string $toState, string $field, ?WorkflowTransition $knownTransition = null): void
    {
        if (! config('filament-flow.enabled', true)) {
            return;
        }

        try {
            // Determine from and to state classes
            $fromStateClass = is_string($fromState) ? $fromState : get_class($fromState);
            $toStateClass = is_string($toState) ? $toState : get_class($toState);

            // Try to get workflow for this model (Database-First approach, with tenant fallback)
            $workflow = Workflow::findForModel(static::class, $field);

            // Initialize variables for workflow-related data
            $workflowId = null;
            $transitionId = null;
            $fromStateLabel = null;
            $toStateLabel = null;

            if ($workflow) {
                // Database-First approach: get labels from workflow states
                $fromWorkflowState = $this->getWorkflowState($workflow, $fromStateClass);
                $toWorkflowState = $this->getWorkflowState($workflow, $toStateClass);

                $workflowId = $workflow->id;
                $fromStateLabel = $fromWorkflowState?->getAttribute('label');
                $toStateLabel = $toWorkflowState?->getAttribute('label');

                // Use known transition if provided, otherwise find it
                $transitionConfig = $knownTransition ?? $this->findTransitionConfig($workflow, $fromWorkflowState, $toWorkflowState);
                $transitionId = $transitionConfig?->id;
            } else {
                // Code-First approach: try to get labels from State classes
                if ($fromState instanceof State && method_exists($fromState, 'getLabel')) {
                    $fromStateLabel = $fromState->getLabel();
                }
                if ($toState instanceof State && method_exists($toState, 'getLabel')) {
                    $toStateLabel = $toState->getLabel();
                } elseif (is_string($toState)) {
                    // Try to instantiate the state class to get the label
                    try {
                        if (class_exists($toState)) {
                            $tempState = new $toState($this);
                            if (method_exists($tempState, 'getLabel')) {
                                $toStateLabel = $tempState->getLabel();
                            }
                        }
                    } catch (Exception) {
                        // Ignore if we can't get the label
                    }
                }
            }

            // Get current user
            $user = Auth::user();

            // Extract transition notes if enabled
            $notes = $this->extractTransitionNotes();

            // Determine if we have metadata/snapshots to store
            $hasMetadata = ! empty($this->pendingTransitionData);
            $hasSnapshot = true; // Always capture snapshots for audit trail

            // Create transition history record
            // For Code-First: workflow_id, transition_id will be null
            // For Database-First: all fields will be populated
            $historyRecord = WorkflowStateTransition::create([
                'transitionable_type' => static::class,
                'transitionable_id' => $this->getKey(),
                'workflow_id' => $workflowId,
                'transition_id' => $transitionId,
                'from_state' => $fromStateClass,
                'to_state' => $toStateClass,
                'from_state_label' => $fromStateLabel,
                'to_state_label' => $toStateLabel,
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'user_email' => $user?->email,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'notes' => $notes,
                'has_metadata' => $hasMetadata,
                'has_snapshot' => $hasSnapshot,
            ]);

            // Store transition metadata (form data, field changes)
            if ($hasMetadata) {
                WorkflowTransitionMetadata::create([
                    'transition_history_id' => $historyRecord->id,
                    'form_data' => $this->pendingTransitionData,
                ]);
            }

            // Store record snapshots (before and after)
            if ($hasSnapshot) {
                if ($this->preTransitionSnapshot) {
                    WorkflowTransitionSnapshot::create([
                        'transition_history_id' => $historyRecord->id,
                        'snapshot_type' => 'before',
                        'record_data' => $this->preTransitionSnapshot,
                    ]);
                }

                WorkflowTransitionSnapshot::create([
                    'transition_history_id' => $historyRecord->id,
                    'snapshot_type' => 'after',
                    'record_data' => $this->getAttributes(),
                ]);
            }

            // Clear pending transition data after logging
            $this->clearPendingTransitionData();
        } catch (Exception $e) {
            // Log but don't fail if logging fails
            report($e);
        }
    }

    /**
     * Extract transition notes from various sources.
     *
     * Priority order:
     * 1. Transition class getHistoryNotes() method (highest priority)
     * 2. Form field named by config (default: 'transition_notes')
     */
    protected function extractTransitionNotes(): ?string
    {
        // Check if logging notes is enabled
        if (! config('filament-flow.log_transition_notes', true)) {
            return null;
        }

        // Priority 1: Check if transition instance has getHistoryNotes() method
        if ($this->pendingTransitionInstance !== null) {
            if (method_exists($this->pendingTransitionInstance, 'getHistoryNotes')) {
                $notes = $this->pendingTransitionInstance->getHistoryNotes();
                if ($notes !== null && $notes !== '') {
                    return $notes;
                }
            }
        }

        // Priority 2: Check for configured field in transition data
        if ($this->pendingTransitionData !== null) {
            $notesField = config('filament-flow.transition_notes_field', 'transition_notes');

            if ($notesField !== null && isset($this->pendingTransitionData[$notesField])) {
                $notes = $this->pendingTransitionData[$notesField];
                if ($notes !== null && $notes !== '') {
                    return $notes;
                }
            }
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
     * @deprecated Use autoDetectTransitionInstance instead. This method is kept for backwards compatibility.
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
     * Enforce access control for transitions
     *
     * This method checks if the current user (or specified user) is authorized
     * to perform the transition. If enforcement is enabled and the user is not
     * authorized, an UnauthorizedTransitionException is thrown.
     *
     * @param  string|State  $toState  Target state
     *
     * @throws UnauthorizedTransitionException
     */
    protected function enforceTransitionAccess(string|State $toState): void
    {
        // Check if enforcement is enabled
        if (! config('filament-flow.state_access.enforce_on_transition', true)) {
            return;
        }

        // Check if state access control is enabled at all
        if (! config('filament-flow.state_access.enabled', true)) {
            return;
        }

        // Check if the model uses HasStateAccess trait
        if (! method_exists($this, 'canBeTransitionedBy')) {
            return;
        }

        // Get the user (from asUser() or current authenticated user)
        $user = $this->transitionUser ?? Auth::user();

        // Get current state for error message
        $currentState = $this->state;
        $fromStateClass = is_string($currentState) ? $currentState : get_class($currentState);
        $toStateClass = is_string($toState) ? $toState : get_class($toState);

        // Check access
        if (! $this->canBeTransitionedBy($user, $toStateClass)) {
            // Clear transition user for next call
            $this->transitionUser = null;

            throw new UnauthorizedTransitionException(
                $this,
                $fromStateClass,
                $toStateClass,
                $user
            );
        }

        // Do NOT clear transitionUser here — it is still needed by
        // checkTransitionPermissions() during the actual transition execution.
        // It will be cleared in clearPendingTransitionData() after logging.
    }

    /**
     * Perform transition without access control enforcement
     *
     * Use this method when you need to bypass access control checks,
     * for example in system-level operations or scheduled tasks.
     *
     * @param  string|State  $state  Target state
     * @param  mixed  ...$arguments  Transition data
     *
     * @throws Exception
     * @throws Throwable
     */
    public function forceTransitionTo(string|State $state, ...$arguments): static
    {
        // Temporarily disable enforcement
        $originalValue = config('filament-flow.state_access.enforce_on_transition');
        config(['filament-flow.state_access.enforce_on_transition' => false]);

        try {
            return $this->transitionTo($state, ...$arguments);
        } finally {
            // Restore original value
            config(['filament-flow.state_access.enforce_on_transition' => $originalValue]);
        }
    }

    /**
     * @return $this
     */
    protected function executeTheTransition(State|string $toState, string $field, string $toStateClass, string $fromState): static
    {
        if (is_string($toState)) {
            // Database-only state: update directly in database to bypass all casts/accessors
            DB::table($this->getTable())
                ->where($this->getKeyName(), $this->getKey())
                ->update([$field => $toState]);

            // Update the model's attributes to reflect the change
            $this->attributes[$field] = $toState;
            // Clear the class cast cache for this field so Spatie doesn't re-apply casting
            if (isset($this->classCastCache[$field])) {
                unset($this->classCastCache[$field]);
            }
        } else {
            // PHP State class: instantiate it
            $this->{$field} = new $toStateClass($this);
            // Save the model
            $this->save();
        }

        // Find transition config for side effects
        $transitionConfig = null;
        try {
            $workflow = Workflow::findForModel(static::class, $field);
            if ($workflow) {
                $fromWorkflowState = $this->getWorkflowState($workflow, is_string($fromState) ? $fromState : get_class($fromState));
                $toWorkflowState = $this->getWorkflowState($workflow, $toStateClass);
                $transitionConfig = $this->findTransitionConfig($workflow, $fromWorkflowState, $toWorkflowState);
            }
        } catch (Throwable) {
            // Don't fail the transition if we can't find the config for side effects
        }

        // Log the transition
        $this->logTransition($fromState, $toState, $field, $transitionConfig);

        // Execute side effects
        if ($transitionConfig) {
            try {
                app(SideEffectExecutor::class)->execute($this, $transitionConfig);
            } catch (Throwable $e) {
                report($e);
            }
        }

        // Trigger notifications if enabled
        $this->triggerTransitionNotifications($fromState, $toState);

        // Dispatch lifecycle events
        $fromStateClass = is_string($fromState) ? $fromState : get_class($fromState);
        $toStateClass = is_string($toState) ? $toState : get_class($toState);
        $eventUser = $this->transitionUser ?? Auth::user();

        StateExited::dispatch($this, $fromStateClass, $eventUser);
        StateEntered::dispatch($this, $toStateClass, $eventUser);
        TransitionCompleted::dispatch($this, $fromStateClass, $toStateClass, $eventUser, $this->pendingTransitionData ?? []);

        return $this;
    }

    /**
     * Trigger notifications for a transition.
     *
     * Supports both database-first and code-first notifications.
     */
    protected function triggerTransitionNotifications(string|State $fromState, string|State $toState): void
    {
        // Check if notifications are enabled
        if (! config('filament-flow.notifications.enabled', true)) {
            return;
        }

        try {
            $fromStateClass = is_string($fromState) ? $fromState : get_class($fromState);
            $toStateClass = is_string($toState) ? $toState : get_class($toState);

            $notificationService = app(NotificationService::class);
            $notificationService->triggerForTransition(
                $this,
                $fromStateClass,
                $toStateClass,
                $this->pendingTransitionData ?? [],
                $this->pendingTransitionInstance // Pass transition instance for code-first
            );
        } catch (Exception $e) {
            // Don't fail the transition if notification fails
            report($e);
        }
    }
}
