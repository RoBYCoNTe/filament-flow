<?php

namespace RoBYCoNTe\FilamentFlow\Tables\Columns;

use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Support\OwnershipHistory;
use RoBYCoNTe\FilamentFlow\Support\RecordOwner;
use RoBYCoNTe\FilamentFlow\Support\UserSummary;

/**
 * The column that shows who holds a record — and, beside the name, that the record changed
 * hands: how many times, from whom, and when. The history is read from
 * `workflow_owner_changes`, written every time the panel hands a record over.
 *
 * The owner lives in the column named by `state_access.owner_field` (a `user_id`, by
 * default); a record may have nobody, and the column says so with a dash.
 */
class OwnerColumn extends Column
{
    protected string $view = 'filament-flow::tables.columns.owner-column';

    /** How many handovers the tooltip lists before it says "and more". */
    protected int $historyLimit = 5;

    protected bool $withHistory = true;

    protected bool $inlinesLastChange = true;

    /**
     * The words the host uses for its own roles, keyed by role name.
     *
     * @var array<string, string>
     */
    protected array $roleLabels = [];

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function historyLimit(int $limit): static
    {
        $this->historyLimit = $limit;

        return $this;
    }

    public function getHistoryLimit(): int
    {
        return $this->historyLimit;
    }

    /** Whether the handovers are read at all: a host may want the bare name. */
    public function withHistory(bool $condition = true): static
    {
        $this->withHistory = $condition;

        return $this;
    }

    public function getWithHistory(): bool
    {
        return $this->withHistory;
    }

    /** Whether the last handover is written under the name, not only in the tooltip. */
    public function inlinesLastChange(bool $condition = true): static
    {
        $this->inlinesLastChange = $condition;

        return $this;
    }

    public function getInlinesLastChange(): bool
    {
        return $this->inlinesLastChange;
    }

    /** @param array<string, string> $labels */
    public function roleLabels(array $labels): static
    {
        $this->roleLabels = $labels;

        return $this;
    }

    /** The column the owner sits in, as the package is configured. */
    public function ownerField(): string
    {
        return RecordOwner::field();
    }

    /**
     * Who holds the record now, in the shape the column reads.
     *
     * @return array{id:int|string,name:string,initials:string,roles:string}|null
     */
    public function getOwner(?Model $record = null): ?array
    {
        $record ??= $this->getRecord();

        if (! $record) {
            return null;
        }

        $owner = RecordOwner::of($record);

        if ($owner === null) {
            return null;
        }

        return UserSummary::of($owner, $this->roleLabels);
    }

    /**
     * The handovers of the record, most recent first, as the ownership history reads them.
     *
     * @return list<array<string, mixed>>
     */
    public function getOwnerChanges(?Model $record = null): array
    {
        $record ??= $this->getRecord();

        return $record ? OwnershipHistory::for($record) : [];
    }
}
