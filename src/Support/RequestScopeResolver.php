<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\FieldListProviderInterface;
use RoBYCoNTe\FilamentFlow\Contracts\ResolvesRequestScope;
use RoBYCoNTe\FilamentFlow\Definition\RequestScope;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use WeakMap;

/**
 * Reads the scope a record has from its history: the requests still open, in the state they
 * sent the record to, whose answering role is one the user acts with.
 *
 * Nothing is stored for it. What the requester chose lives in the history row of the transition
 * that opened the request, what the call allowed lives in the transition, and the request stops
 * counting the moment a later transition answers it or the record leaves the state it was sent
 * to. Several requests add up: the fields of all of them are open, and one exclusive request is
 * enough for the rest to stay frozen.
 *
 * Permissions are read field by field, so both reads are remembered — what a workflow declares
 * for as long as the resolver lives, what a record has for as long as the record stays in the
 * same state.
 */
final class RequestScopeResolver implements ResolvesRequestScope
{
    /** @var array<int,array<string,RequestScope>> declarations by workflow id, then transition name */
    private array $declarations = [];

    /** @var WeakMap<Model, array{state: string, requests: list<array{scope: RequestScope, paths: list<string>}>}> */
    private WeakMap $requests;

    public function __construct(private readonly OpenRequests $openRequests)
    {
        $this->requests = new WeakMap;
    }

    public function declaresScope(Model $record): bool
    {
        $workflow = $this->workflowOf($record);

        return $workflow !== null && $this->declarationsOf($workflow) !== [];
    }

    public function scopeFor(Model $record, ?Model $user, array $effectiveRoles = []): ?array
    {
        if ($user === null) {
            return null;
        }

        $applying = array_values(array_filter(
            $this->openOf($record),
            static fn (array $request): bool => in_array($request['scope']->answeredByRole(), $effectiveRoles, true),
        ));

        if ($applying === []) {
            return null;
        }

        $paths = [];
        $whitelist = [];
        $unbounded = false;
        $exclusive = false;

        foreach ($applying as $request) {
            array_push($paths, ...$request['paths']);
            $exclusive = $exclusive || $request['scope']->isExclusive();

            $only = $request['scope']->whitelist();
            $unbounded = $unbounded || $only === null;

            if ($only !== null) {
                array_push($whitelist, ...$only);
            }
        }

        return [
            'mode' => $exclusive ? RequestScope::MODE_EXCLUSIVE : RequestScope::MODE_ADDITIVE,
            'paths' => array_values(array_unique($paths)),
            'whitelist' => $unbounded ? null : array_values(array_unique($whitelist)),
            'universe' => $this->universeOf($record),
        ];
    }

    /** Forgets what was remembered: after a sync of the workflow, or between two tests. */
    public function flush(): void
    {
        $this->declarations = [];
        $this->requests = new WeakMap;
    }

    /**
     * The requests of the record that still hold, each with the fields the call still allows.
     *
     * @return list<array{scope: RequestScope, paths: list<string>}>
     */
    private function openOf(Model $record): array
    {
        $workflow = $this->workflowOf($record);

        if ($workflow === null) {
            return [];
        }

        $state = (string) $record->getAttribute($workflow->state_column);
        $remembered = $this->requests[$record] ?? null;

        if ($remembered !== null && $remembered['state'] === $state) {
            return $remembered['requests'];
        }

        $declarations = $this->declarationsOf($workflow);
        $open = [];

        if ($declarations !== []) {
            foreach ($this->openRequests->forRecord($record) as $request) {
                $scope = $declarations[$request->transitionName] ?? null;

                // Only the request that sent the record to this very state counts, and only
                // what the call allows of what the requester chose.
                if ($scope === null || ! $request->isOpen() || ! $request->hasScope() || $request->toState !== $state) {
                    continue;
                }

                $paths = array_values(array_filter(
                    (array) ($request->scope['paths'] ?? []),
                    static fn (mixed $path): bool => is_string($path) && $scope->allows($path),
                ));

                if ($paths !== []) {
                    $open[] = ['scope' => $scope, 'paths' => $paths];
                }
            }
        }

        $this->requests[$record] = ['state' => $state, 'requests' => $open];

        return $open;
    }

    /**
     * What the call can choose from, so the exclusive mode can freeze what has no rule.
     *
     * @return list<string>
     */
    private function universeOf(Model $record): array
    {
        if (! app()->bound(FieldListProviderInterface::class)) {
            return [];
        }

        return array_values(app(FieldListProviderInterface::class)->getFields($record));
    }

    private function workflowOf(Model $record): ?Workflow
    {
        $tenantId = method_exists($record, 'getWorkflowTenantId') ? $record->getWorkflowTenantId() : null;

        return Workflow::findForModel($record::class, 'state', $tenantId);
    }

    /**
     * @return array<string,RequestScope>
     */
    private function declarationsOf(Workflow $workflow): array
    {
        return $this->declarations[$workflow->id] ??= WorkflowTransition::query()
            ->where('workflow_id', $workflow->id)
            ->get(['name', 'metadata'])
            ->filter(static fn (WorkflowTransition $transition): bool => is_array($transition->metadata['request_scope'] ?? null))
            ->mapWithKeys(static fn (WorkflowTransition $transition): array => [
                $transition->name => RequestScope::fromArray($transition->metadata['request_scope']),
            ])
            ->all();
    }
}
