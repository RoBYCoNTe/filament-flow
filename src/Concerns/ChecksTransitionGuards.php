<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use RoBYCoNTe\FilamentFlow\Definition\RequestScope;
use RoBYCoNTe\FilamentFlow\Exceptions\UnauthorizedTransitionException;
use RoBYCoNTe\FilamentFlow\Exceptions\WorkflowValidationException;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Services\WorkflowValidationService;
use RoBYCoNTe\FilamentFlow\Support\OpenRequest;
use RoBYCoNTe\FilamentFlow\Support\OpenRequests;
use RoBYCoNTe\FilamentFlow\Support\RequestScopeRecorder;
use RoBYCoNTe\FilamentFlow\Validation\ValidationResult;
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

        if ($result->isNotEmpty()) {
            throw new WorkflowValidationException($result);
        }
    }

    /**
     * What the office picked for the request this transition opens must be what the call
     * declared. It is checked whatever the validation level of the transition: a draft saved
     * for later still cannot open a field the call never offered.
     *
     * @param  array<array-key,mixed>  $payload
     */
    protected function validateRequestScopePayload(string|State $toState, array $payload = []): void
    {
        $scope = $this->declaredRequestScope($toState);

        if ($scope === null) {
            return;
        }

        $errors = RequestScopeRecorder::errors($scope, $payload);

        if ($errors !== []) {
            throw new WorkflowValidationException(new ValidationResult($errors));
        }
    }

    /**
     * An answer that the office asked to carry a change must carry one: when the transition
     * answers a request that was opened with `requireChange()`, at least one of the fields it
     * unlocked has to differ from what it held when the request was made.
     *
     * The error sits on the first field the office unlocked, so the recap of the validation
     * shows it in the step where the applicant has to act and the link leads there. A transition
     * that does not ask for the workflow rules (a draft saved for later) is not held to it.
     *
     * @param  array<array-key,mixed>  $payload
     *
     * @throws WorkflowValidationException
     */
    protected function validateRequestAnswer(string|State $toState, array $payload = []): void
    {
        if ($this->skipTransitionValidation || ! config('filament-flow.validation.enabled', true)) {
            return;
        }

        $workflow = Workflow::findForModel(static::class, 'state', $this->getWorkflowTenantId());

        if ($workflow === null) {
            return;
        }

        $fromState = $this->getWorkflowState($workflow, (string) $this->getAttribute($workflow->state_column));
        $targetState = $this->getWorkflowState(
            $workflow,
            $toState instanceof State ? $toState::getMorphClass() : $toState,
        );

        $transition = $this->findTransitionConfig($workflow, $fromState, $targetState);

        if (! (data_get($transition, 'metadata.answers_request', false)) || ! ($transition?->validationLevel()?->requiresWorkflowRules() ?? true)) {
            return;
        }

        // The oldest open request is the one an answer closes.
        $request = app(OpenRequests::class)->forRecord($this)
            ->filter(fn (OpenRequest $open): bool => $open->isOpen())
            ->last();

        if ($request === null || ! $request->requiresChange()) {
            return;
        }

        $values = [];

        foreach ((array) config('filament-flow.field_changes.attribute', 'form_data') as $attribute) {
            if (is_string($attribute) && $attribute !== '') {
                $values += $this->decodeFieldAttribute(data_get($this->getAttributes(), $attribute), $attribute);
            }
        }

        // What the answer carries wins over what the record held.
        $values = array_replace_recursive($values, RequestScopeRecorder::withoutPick($payload));

        $unchanged = $request->unchangedPaths($values);

        if (count($unchanged) < count((array) $request->scope['paths'])) {
            return;
        }

        throw new WorkflowValidationException(new ValidationResult([
            (string) $request->scope['paths'][0] => [__('filament-flow::messages.request_change_required')],
        ]));
    }

    /**
     * The request scope the transition about to run declares, if it opens a request.
     */
    protected function declaredRequestScope(string|State $toState): ?RequestScope
    {
        $workflow = Workflow::findForModel(static::class, 'state', $this->getWorkflowTenantId());

        if ($workflow === null) {
            return null;
        }

        $fromState = $this->getWorkflowState($workflow, (string) $this->getAttribute($workflow->state_column));
        $targetState = $this->getWorkflowState(
            $workflow,
            $toState instanceof State ? $toState::getMorphClass() : $toState,
        );

        return $this->requestScopeOf($this->findTransitionConfig($workflow, $fromState, $targetState));
    }

    /**
     * The scope a stored transition declares: only one that opens a request carries it.
     */
    protected function requestScopeOf(?object $transition): ?RequestScope
    {
        $metadata = (array) data_get($transition, 'metadata', []);

        if (! ($metadata['opens_request'] ?? false) || ! is_array($metadata['request_scope'] ?? null)) {
            return null;
        }

        return RequestScope::fromArray($metadata['request_scope']);
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

    /**
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
}
