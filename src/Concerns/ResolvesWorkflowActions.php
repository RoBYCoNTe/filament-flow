<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use RoBYCoNTe\FilamentFlow\Events\TransitionCompleted;
use RoBYCoNTe\FilamentFlow\Exceptions\ActionNotFoundException;
use RoBYCoNTe\FilamentFlow\Exceptions\ConditionNotMetException;
use RoBYCoNTe\FilamentFlow\Exceptions\FormulaConditionFailedException;
use RoBYCoNTe\FilamentFlow\Exceptions\UnauthorizedTransitionException;
use RoBYCoNTe\FilamentFlow\Exceptions\WorkflowNotFoundException;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Services\ConditionEvaluator;
use RoBYCoNTe\FilamentFlow\Services\SideEffectExecutor;
use RoBYCoNTe\FilamentFlow\Services\TransitionFormService;
use Spatie\ModelStates\State;
use Throwable;

/**
 * The actions available on a model, and running one of them.
 *
 * An action is a transition that stays where it is: the same rules, the same data, the
 * same permissions — but the state does not move, which is why it needs its own small
 * path through the machinery.
 */
trait ResolvesWorkflowActions
{
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
     * @throws WorkflowNotFoundException
     * @throws ActionNotFoundException
     * @throws UnauthorizedTransitionException
     * @throws ConditionNotMetException
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

        $workflow = Workflow::findForModel(static::class, $field, $this->getWorkflowTenantId());

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

        // Check conditions — formula failures carry an interpolated message
        try {
            if (! app(ConditionEvaluator::class)->evaluate($this, $transition->conditions)) {
                throw new ConditionNotMetException($transitionName);
            }
        } catch (FormulaConditionFailedException $e) {
            throw new ConditionNotMetException($transitionName, $e->getMessage());
        }

        // Same validation pass as a state-changing transition: an in-state action
        // may record data, so the rules of the current state apply here too.
        $this->validateTransitionPayload($currentStateClass, $this->pendingTransitionData ?? []);

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

        $workflow = Workflow::findForModel(static::class, $field, $this->getWorkflowTenantId());

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
                if (! $this->checkTransitionPermissions($transition)) {
                    return false;
                }

                try {
                    return app(ConditionEvaluator::class)->evaluate($this, $transition->conditions);
                } catch (FormulaConditionFailedException) {
                    return false;
                }
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

        $workflow = Workflow::findForModel(static::class, $field, $this->getWorkflowTenantId());

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

                try {
                    return app(ConditionEvaluator::class)->evaluate($this, $transition->conditions);
                } catch (FormulaConditionFailedException) {
                    return false;
                }
            })
            ->values();
    }
}
