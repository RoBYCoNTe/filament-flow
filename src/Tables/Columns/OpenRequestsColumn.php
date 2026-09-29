<?php

namespace RoBYCoNTe\FilamentFlow\Tables\Columns;

use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Support\OpenRequest;
use RoBYCoNTe\FilamentFlow\Support\OpenRequests;

/**
 * The column that tells, at a glance, that a record awaits an answer: the request an earlier
 * transition opened and no later one answered.
 *
 * It reads the same history the entry reads — the note and the term declared by the transition
 * that asked — one record per row: a list of a hundred rows pays a query per row, which is the
 * price of a column that says something the row itself does not carry. It is toggleable, so a
 * list can offer it and each reader can take it away.
 */
class OpenRequestsColumn extends Column
{
    protected string $view = 'filament-flow::tables.columns.open-requests-column';

    /** The path of the note, when the host wants to override the one the transition declared. */
    protected ?string $noteField = null;

    /** The path of the term, when the host wants to override the one the transition declared. */
    protected ?string $deadlineField = null;

    /** Whether the term is read beside the request. */
    protected bool $showDeadline = true;

    public static function make(?string $name = 'open_requests'): static
    {
        // A column the list carries and the reader may put away: the choice belongs to whoever
        // reads the list, not to the call that declares it.
        return parent::make($name)
            ->label(__('filament-flow::messages.open_requests_label'))
            ->toggleable();
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

    /**
     * The request a row awaits, or nothing: the newest one still open.
     */
    public function getRequest(?Model $record = null): ?OpenRequest
    {
        $record ??= $this->getRecord();

        if (! $record instanceof Model) {
            return null;
        }

        return app(OpenRequests::class)->forRecord($record, [
            'note_field' => $this->noteField,
            'deadline_field' => $this->deadlineField,
            'show_deadline' => $this->showDeadline,
            'limit' => 1,
        ])->first();
    }
}
