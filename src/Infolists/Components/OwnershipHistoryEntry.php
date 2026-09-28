<?php

namespace RoBYCoNTe\FilamentFlow\Infolists\Components;

use Filament\Infolists\Components\Entry;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Support\OwnershipHistory;

/**
 * The history of a record's handovers: who held it, who holds it now, when, what the previous
 * holder kept and who made the change — the same story the owner column carries in the list,
 * told in full where there is room for it.
 *
 * ```php
 * OwnershipHistoryEntry::make('ownership_history');
 * ```
 *
 * A record that never changed hands says so in one line, instead of showing nothing at all.
 */
class OwnershipHistoryEntry extends Entry
{
    protected string $view = 'filament-flow::infolists.ownership-history';

    /** How many handovers are listed; null lists them all. */
    protected ?int $limit = null;

    protected bool $withTimeline = true;

    public static function make(?string $name = 'ownership-history'): static
    {
        return parent::make($name)
            ->label(__('filament-flow::messages.ownership_history_label'));
    }

    public function limit(?int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /** Whether the entries are strung on a timeline, or listed plainly. */
    public function timeline(bool $condition = true): static
    {
        $this->withTimeline = $condition;

        return $this;
    }

    public function getWithTimeline(): bool
    {
        return $this->withTimeline;
    }

    /**
     * The handovers of the record, most recent first.
     *
     * @return list<array<string,mixed>>
     */
    public function getHistory(?Model $record = null): array
    {
        $record ??= $this->getRecord();

        if (! $record) {
            return [];
        }

        return OwnershipHistory::for($record, $this->limit);
    }
}
