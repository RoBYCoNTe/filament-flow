<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use DB;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use RoBYCoNTe\FilamentFlow\Events\StateEntered;
use RoBYCoNTe\FilamentFlow\Events\StateExited;
use RoBYCoNTe\FilamentFlow\Events\TransitionCompleted;
use RoBYCoNTe\FilamentFlow\Exceptions\InvalidStateException;
use RoBYCoNTe\FilamentFlow\Exceptions\UnauthorizedTransitionException;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Services\SideEffectExecutor;
use RoBYCoNTe\FilamentFlow\Services\TransitionFormService;
use Spatie\ModelStates\Exceptions\TransitionNotFound;
use Spatie\ModelStates\State;
use Throwable;

/**
 * The way a record moves: `transitionTo()` finds the transition declared between two states,
 * checks whether this user may take it, runs it and writes what came out — the state column,
 * the side effects, and the values the transition asked for.
 *
 * One method covers a state named as a string, a state class, and an action that stays where it
 * is. The tenant is read from the record, because with one workflow per owner the lookup has to
 * know **which** workflow it is asking.
 */
trait HasDatabaseTransitions
{
    use ChecksTransitionGuards;
    use ChecksTransitionPermissions;
    use LogsTransitionHistory;
    use ResolvesWorkflowActions;
    use ResolvesWorkflowStates;
    use TriggersTransitionNotifications;

    /** Set by forceTransitionTo(): the explicit escape hatch skips validation. */
    private bool $skipTransitionValidation = false;

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

            $tenantId = method_exists($model, 'getWorkflowTenantId') ? $model->getWorkflowTenantId() : null;
            $workflow = Workflow::findForModel(static::class, $stateColumn, $tenantId);

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
     * Override transitionTo to handle database transitions
     *
     * @param  string|State  $state  the state the record moves to
     * @param  mixed  ...$arguments  the values the transition asks for, when it asks for any
     *
     * @throws Exception
     * @throws Throwable
     * @throws UnauthorizedTransitionException when the access rules of the state refuse this user
     * @throws InvalidStateException when the target state is not part of the workflow
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

        // The rules of the target state are part of the transition: whoever runs
        // it (form, API, console) cannot skip them.
        $this->validateTransitionPayload(
            $state,
            is_array($arguments[0] ?? null) ? $arguments[0] : [],
        );

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

        // HYBRID APPROACH: Check if transition exists in database before trying Spatie This
        // allows mixing Code-First (Spatie) and Database-First (database configured)
        // transitions
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
        $workflow = Workflow::findForModel(static::class, $field, $this->getWorkflowTenantId());

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
        // Temporarily disable enforcement and validation
        $originalValue = config('filament-flow.state_access.enforce_on_transition');
        config(['filament-flow.state_access.enforce_on_transition' => false]);
        $this->skipTransitionValidation = true;

        try {
            return $this->transitionTo($state, ...$arguments);
        } finally {
            $this->skipTransitionValidation = false;

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
            $workflow = Workflow::findForModel(static::class, $field, $this->getWorkflowTenantId());
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
}
