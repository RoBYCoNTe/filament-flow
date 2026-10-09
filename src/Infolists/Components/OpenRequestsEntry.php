<?php

namespace RoBYCoNTe\FilamentFlow\Infolists\Components;

use Filament\Infolists\Components\Entry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use RoBYCoNTe\FilamentFlow\Contracts\StoresRequestAttachments;
use RoBYCoNTe\FilamentFlow\Support\LocalizedDate;
use RoBYCoNTe\FilamentFlow\Support\OpenRequest;
use RoBYCoNTe\FilamentFlow\Support\OpenRequests;
use RoBYCoNTe\FilamentFlow\Support\RecordOwner;
use RoBYCoNTe\FilamentFlow\Support\RequestScopeLabels;

/**
 * What the workflow is waiting for on a record: the transitions that asked something and no
 * later one answered, read from the history the engine already keeps — who asked, when, with
 * which note and which term.
 *
 * It guides rather than acts: the reader is told what is expected and by when, while the
 * buttons that move the record stay where they have always been, in the toolbar of the state
 * actions. Nothing is stored for it, and a workflow that marks no transition with
 * `opensRequest()` stays silent.
 *
 * The note and the term are not columns of the engine: they are paths of the host, read from
 * the record (and, first, from what the transition carried), so nothing new has to be managed.
 */
class OpenRequestsEntry extends Entry
{
    protected string $view = 'filament-flow::infolists.open-requests';

    /** The path of the note the request carries, in the words of the host. */
    protected ?string $noteField = null;

    /** The path of the term the request carries, in the words of the host. */
    protected ?string $deadlineField = null;

    /**
     * The transitions that open a request, when the host prefers naming them here to marking
     * them with `opensRequest()`.
     *
     * @var list<string>
     */
    protected array $openTransitions = [];

    /**
     * The transitions that answer a request, when the host prefers naming them here to marking
     * them with `answersRequest()`.
     *
     * @var list<string>
     */
    protected array $answerTransitions = [];

    /** Whether the last answered exchange is read too, under the open ones. */
    protected bool $showAnswered = false;

    /** How many exchanges are shown at most, newest first. */
    protected ?int $limit = null;

    /** Whether the term of the request is read beside it. */
    protected bool $showDeadline = true;

    public static function make(?string $name = 'open-requests'): static
    {
        // A name humanised by the framework ("Flow open requests") says nothing: the label
        // starts translated, and the host may still name it as it likes.
        return parent::make($name)
            ->label(__('filament-flow::messages.open_requests_label'));
    }

    public function noteField(?string $field): static
    {
        $this->noteField = $field;

        return $this;
    }

    public function deadlineField(?string $field): static
    {
        $this->deadlineField = $field;

        return $this;
    }

    public function hideDeadline(bool $hidden = true): static
    {
        $this->showDeadline = ! $hidden;

        return $this;
    }

    /** @param list<string> $open @param list<string> $answer */
    public function transitions(array $open, array $answer = []): static
    {
        $this->openTransitions = array_values($open);
        $this->answerTransitions = array_values($answer);

        return $this;
    }

    /** @param list<string> $names */
    public function openTransitions(array $names): static
    {
        $this->openTransitions = array_values($names);

        return $this;
    }

    /** @param list<string> $names */
    public function answerTransitions(array $names): static
    {
        $this->answerTransitions = array_values($names);

        return $this;
    }

    public function showAnswered(bool $show = true): static
    {
        $this->showAnswered = $show;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * The exchanges of the record, newest first: the open ones, plus the answered ones when the
     * host asks for them.
     *
     * @return Collection<int, OpenRequest>
     */
    public function getOpenRequests(): Collection
    {
        $record = $this->getRecord();

        if (! $record instanceof Model) {
            return collect();
        }

        return $this->requestsFor($record);
    }

    /**
     * The exchanges of a record, read outside a rendered entry: the same reading the view
     * gets, for a host that wants to ask on its own.
     *
     * @return Collection<int, OpenRequest>
     */
    public function requestsFor(Model $record): Collection
    {
        return app(OpenRequests::class)->forRecord($record, [
            'note_field' => $this->noteField,
            'deadline_field' => $this->deadlineField,
            'show_deadline' => $this->showDeadline,
            'open_transitions' => $this->openTransitions,
            'answer_transitions' => $this->answerTransitions,
            'include_answered' => $this->showAnswered,
            'limit' => $this->limit,
        ]);
    }

    /**
     * Every exchange of the record, answered ones included, oldest last: what the recap of the
     * entry folds away. It is read apart from the open requests, so a host that shows only
     * what is pending still lets the reader look back.
     *
     * @return Collection<int, OpenRequest>
     */
    public function getExchangeRecap(): Collection
    {
        $record = $this->getRecord();

        if (! $record instanceof Model) {
            return collect();
        }

        $exchanges = app(OpenRequests::class)->forRecord($record, [
            'note_field' => $this->noteField,
            'deadline_field' => $this->deadlineField,
            'show_deadline' => $this->showDeadline,
            'open_transitions' => $this->openTransitions,
            'answer_transitions' => $this->answerTransitions,
            'include_answered' => true,
        ]);

        // A recap is for looking back: it appears once there is something to look back at.
        return $exchanges->contains(fn (OpenRequest $request): bool => ! $request->isMessage() && ! $request->isOpen())
            ? $exchanges
            : collect();
    }

    /**
     * The fields a request opened, by the label a person reads.
     *
     * @return list<string>
     */
    public function scopeLabelsFor(OpenRequest $request): array
    {
        $record = $this->getRecord();

        if (! $record instanceof Model || ! $request->hasScope()) {
            return [];
        }

        return RequestScopeLabels::for($record, array_map('strval', (array) $request->scope['paths']));
    }

    /**
     * The documents the requester attached to a request, as the host hands them over. Nothing when
     * the host keeps no attachments or the request carries none.
     *
     * @return list<array{id: int|string, name: string, url: string}>
     */
    public function attachmentsFor(OpenRequest $request, ?Model $record = null): array
    {
        $record ??= $this->getRecord();

        if (! $record instanceof Model || $request->attachmentIds() === [] || ! app()->bound(StoresRequestAttachments::class)) {
            return [];
        }

        return app(StoresRequestAttachments::class)->documents($record, $request->attachmentIds());
    }

    /** Whether the reader is the owner of the record: what is expected is theirs to answer. */
    public function getIsForOwner(): bool
    {
        $record = $this->getRecord();
        $user = Auth::user();

        if (! $record instanceof Model || $user === null) {
            return false;
        }

        $ownerId = RecordOwner::id($record);

        return $ownerId !== null && (string) $ownerId === (string) $user->getKey();
    }

    /** The same rule the timeline follows: the locale decides, unless the host says otherwise. */
    public function getDateTimeFormat(): string
    {
        return LocalizedDate::dateTime();
    }

    public function getDateFormat(): string
    {
        return LocalizedDate::date();
    }
}
