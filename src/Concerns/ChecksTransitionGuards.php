<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use RoBYCoNTe\FilamentFlow\Exceptions\UnauthorizedTransitionException;
use RoBYCoNTe\FilamentFlow\Exceptions\WorkflowValidationException;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Services\WorkflowValidationService;
use Spatie\ModelStates\State;

/**
 * The guards a transition must pass before it happens.
 *
 * Two questions, asked in this order and both fatal: is the payload of this transition
 * complete and valid, and is the actor allowed past the state access rules? They are kept
 * together because they are the same moment — the moment before anything is written.
 */
trait ChecksTransitionGuards
{
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
    /**
     * Runs the validation pass that guards the transition: the field rules of
     * the state the record is *in* (required included), the rules declared on
     * the transition itself, the host field rules and the expression rules.
     *
     * The rules of the state being left are the ones that apply, because they
     * describe what must hold to move on: the reviewer fills the score while in
     * `under_review`, and `approve` checks it. The permissions of the target
     * state are a rendering concern (they apply as soon as the record is there).
     *
     * @param  array<string,mixed>  $payload  data carried by the transition
     *
     * @throws WorkflowValidationException when the data does not satisfy the rules
     */
    protected function validateTransitionPayload(string|State $toState, array $payload = []): void
    {
        if ($this->skipTransitionValidation || ! config('filament-flow.validation.enabled', true)) {
            return;
        }

        // A "save and continue later" step accepts whatever was typed so far.
        if (! $this->transitionRequiresValidation($toState)) {
            return;
        }

        $toStateName = $toState instanceof State ? $toState::getMorphClass() : $toState;

        $workflow = Workflow::findForModel(static::class, 'state', $this->getWorkflowTenantId());

        if ($workflow === null) {
            return;
        }

        $fromState = $this->getWorkflowState($workflow, (string) $this->getAttribute($workflow->state_column));
        $targetState = $this->getWorkflowState($workflow, $toStateName);

        $transition = $this->findTransitionConfig($workflow, $fromState, $targetState);

        $result = app(WorkflowValidationService::class)->validatePayload(
            $this,
            $this->transitionUser ?? Auth::user(),
            $payload,
            $transition,
        );

        file_put_contents('/tmp/x.log', 'ENGINE errors='.json_encode($result->errors()).PHP_EOL, FILE_APPEND);

        if ($result->isNotEmpty()) {
            throw new WorkflowValidationException($result);
        }
    }

    /**
     * True when the transition that is being run declares that every rule has to
     * hold (the default). A transition with a lower level — a draft saved for
     * later — is not validated by the workflow.
     */
    protected function transitionRequiresValidation(string|State $toState): bool
    {
        $workflow = Workflow::findForModel(static::class, 'state', $this->getWorkflowTenantId());

        if ($workflow === null) {
            return true;
        }

        $fromState = $this->getWorkflowState($workflow, (string) $this->getAttribute($workflow->state_column));
        $targetState = $this->getWorkflowState(
            $workflow,
            $toState instanceof State ? $toState::getMorphClass() : $toState,
        );

        $transition = $this->findTransitionConfig($workflow, $fromState, $targetState);

        return $transition?->validationLevel()?->requiresWorkflowRules() ?? true;
    }

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
}
