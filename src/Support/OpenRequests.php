<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Services\StateService;

/**
 * Reads the exchanges between the sides of a workflow from the history of a record: which
 * transitions asked something, which ones answered, and which ones left a message with no answer
 * expected, in the order they happened.
 *
 * Nothing is stored for this reading: a request exists while the history says it was opened and
 * a later transition has not answered it — the same history the timeline already holds. A
 * workflow that marks no transition with `opensRequest()`/`answersRequest()`/`leavesMessage()`
 * answers with an empty collection, which is how the components stay quiet where there is
 * nothing to say.
 */
final class OpenRequests
{
    /**
     * The exchanges of a record, newest first.
     *
     * @param  array{
     *     note_field?: string|null,
     *     deadline_field?: string|null,
     *     show_deadline?: bool,
     *     open_transitions?: list<string>,
     *     answer_transitions?: list<string>,
     *     message_transitions?: list<string>,
     *     state_column?: string,
     *     include_answered?: bool,
     *     limit?: int|null,
     * }  $options
     * @return Collection<int, OpenRequest>
     */
    public function forRecord(Model $record, array $options = []): Collection
    {
        $rows = WorkflowStateTransition::query()
            ->forRecord($record)
            ->with(['transition', 'metadata'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        /** @var list<array{open: WorkflowStateTransition, answer: WorkflowStateTransition|null, kind: string}> $exchanges */
        $exchanges = [];

        /** @var list<int> $waiting */
        $waiting = [];

        foreach ($rows as $row) {
            if ($this->opens($row, $options)) {
                $exchanges[] = ['open' => $row, 'answer' => null, 'kind' => OpenRequest::KIND_REQUEST];
                $waiting[] = array_key_last($exchanges);

                continue;
            }

            if ($this->leavesMessage($row, $options)) {
                // A message closes nothing and waits for nothing: it stands on its own.
                $exchanges[] = ['open' => $row, 'answer' => null, 'kind' => OpenRequest::KIND_MESSAGE];

                continue;
            }

            if ($this->answers($row, $options) && $waiting !== []) {
                // The answer closes the request that has been waiting the longest.
                $index = array_shift($waiting);
                $exchanges[$index]['answer'] = $row;
            }
        }

        $requests = collect($exchanges)
            ->map(fn (array $exchange): OpenRequest => $this->toRequest($record, $exchange['open'], $exchange['answer'], $options, $exchange['kind']));

        // The open requests and the messages are what the workflow is telling the reader; the
        // answered ones only show when the host asks for them.
        if (! ($options['include_answered'] ?? false)) {
            $requests = $requests
                ->filter(fn (OpenRequest $request): bool => $request->isOpen() || $request->isMessage())
                ->values();
        }

        $limit = $options['limit'] ?? null;

        if (is_int($limit) && $limit > 0) {
            // The newest are the ones that matter: the older ones stay in the history.
            $requests = $requests->slice(-$limit)->values();
        }

        return $requests->reverse()->values();
    }

    /**
     * Whether the row is a transition that opens a request: the names the host lists, or the
     * flag the DSL writes on the transition.
     *
     * @param  array<string, mixed>  $options
     */
    private function opens(WorkflowStateTransition $row, array $options): bool
    {
        /** @var WorkflowTransition|null $transition */
        $transition = $row->transition;
        $names = $options['open_transitions'] ?? [];

        if (is_array($names) && $names !== []) {
            $name = $transition?->name;

            return $name !== null && in_array($name, $names, true);
        }

        return (bool) ($transition?->metadata['opens_request'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function answers(WorkflowStateTransition $row, array $options): bool
    {
        /** @var WorkflowTransition|null $transition */
        $transition = $row->transition;
        $names = $options['answer_transitions'] ?? [];

        if (is_array($names) && $names !== []) {
            $name = $transition?->name;

            return $name !== null && in_array($name, $names, true);
        }

        return (bool) ($transition?->metadata['answers_request'] ?? false);
    }

    /**
     * Whether the row is a transition that leaves a message: the names the host lists, or the
     * flag the DSL writes on the transition.
     *
     * @param  array<string, mixed>  $options
     */
    private function leavesMessage(WorkflowStateTransition $row, array $options): bool
    {
        /** @var WorkflowTransition|null $transition */
        $transition = $row->transition;
        $names = $options['message_transitions'] ?? [];

        if (is_array($names) && $names !== []) {
            $name = $transition?->name;

            return $name !== null && in_array($name, $names, true);
        }

        return (bool) ($transition?->metadata['leaves_message'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function toRequest(
        Model $record,
        WorkflowStateTransition $open,
        ?WorkflowStateTransition $answer,
        array $options,
        string $kind = OpenRequest::KIND_REQUEST,
    ): OpenRequest {
        $transitionName = data_get($open->transition, 'name');
        $transitionLabel = data_get($open->transition, 'label');
        $metadata = (array) data_get($open->transition, 'metadata', []);

        // The paths of the exchange: what the caller set, or what the transition declared with
        // `withRequestFields()` / `leavesMessage()` — so the host declares them once.
        $noteField = $options['note_field'] ?? data_get($metadata, 'note_field');
        $deadlineField = $options['deadline_field'] ?? data_get($metadata, 'deadline_field');
        $showDeadline = $options['show_deadline'] ?? true;

        return new OpenRequest(
            transitionName: is_string($transitionName) && $transitionName !== '' ? $transitionName : (string) $open->to_state,
            label: is_string($transitionLabel) ? $transitionLabel : $open->to_state_label,
            fromState: $open->from_state,
            toState: $open->to_state,
            toStateLabel: $open->to_state_label,
            note: $this->note($record, $open, is_string($noteField) ? $noteField : null),
            deadline: $showDeadline ? $this->deadline($record, is_string($deadlineField) ? $deadlineField : null) : null,
            requestedAt: $open->created_at,
            requestedBy: $open->user_name,
            answeredAt: $answer?->created_at,
            answeredBy: $answer?->user_name,
            answeredByTransition: data_get($answer?->transition, 'name'),
            kind: $kind,
            color: $kind === OpenRequest::KIND_MESSAGE ? $this->colorOf($record, $open->to_state, $options) : null,
            scope: $kind === OpenRequest::KIND_REQUEST ? $this->scopeOf($open) : null,
        );
    }

    /**
     * What the office picked when it opened the request, as the history kept it.
     *
     * @return array<string,mixed>|null
     */
    private function scopeOf(WorkflowStateTransition $open): ?array
    {
        $scope = data_get($open->metadata, 'custom_data.request_scope');

        return is_array($scope) ? $scope : null;
    }

    /**
     * The colour the destination state wears: a decision tells its outcome with the colour of the
     * state it moved to, not with a colour of its own.
     *
     * @param  array<string, mixed>  $options
     */
    private function colorOf(Model $record, ?string $state, array $options): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        $metadata = app(StateService::class)->getStateMetadata(
            $record::class,
            $state,
            is_string($options['state_column'] ?? null) ? $options['state_column'] : 'state',
            method_exists($record, 'getWorkflowTenantId') ? $record->getWorkflowTenantId() : null,
        );

        $color = $metadata['color'] ?? null;

        return is_string($color) && $color !== '' ? $color : null;
    }

    /**
     * The note written with the request: what the transition carried, the notes column the
     * engine keeps, the value the record holds now — in that order.
     */
    private function note(Model $record, WorkflowStateTransition $open, ?string $field): ?string
    {
        if ($field !== null && $field !== '') {
            $carried = data_get($open->metadata?->form_data, $field);

            if (is_string($carried) && trim($carried) !== '') {
                return trim($carried);
            }
        }

        if (is_string($open->notes) && trim($open->notes) !== '') {
            return trim($open->notes);
        }

        if ($field !== null && $field !== '') {
            $held = data_get($record->getAttribute('form_data') ?? [], $field);

            if (is_string($held) && trim($held) !== '') {
                return trim($held);
            }
        }

        return null;
    }

    /** The term the request carries, read from the record the side effect wrote it on. */
    private function deadline(Model $record, ?string $field): ?Carbon
    {
        if ($field === null || $field === '') {
            return null;
        }

        return OpenRequest::parseDate(data_get($record->getAttribute('form_data') ?? [], $field));
    }
}
