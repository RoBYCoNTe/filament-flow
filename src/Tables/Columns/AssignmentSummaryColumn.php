<?php

namespace RoBYCoNTe\FilamentFlow\Tables\Columns;

use Closure;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoBYCoNTe\FilamentFlow\Models\WorkflowAssignment;
use RoBYCoNTe\FilamentFlow\Support\UserSummary;

/**
 * The column that shows who holds a record: the faces of the people assigned, and how many of
 * them fit.
 *
 * The cell is part of the row like any other: clicking it follows the record link of the table,
 * and a host that wants it inert says `->disabledClick()`.
 */
class AssignmentSummaryColumn extends Column
{
    protected string $view = 'filament-flow::tables.columns.assignment-summary-column';

    protected int $avatarLimit = 3;

    protected bool $withAvatarTooltip = true;

    protected ?Closure $avatarDecoratorCallback = null;

    /**
     * The words the host uses for its own roles, keyed by role name: the tooltip of an avatar
     * reads them instead of the names the roles are stored under.
     *
     * @var array<string, string>
     */
    protected array $roleLabels = [];

    public function avatarLimit(int $limit): static
    {
        $this->avatarLimit = $limit;

        return $this;
    }

    public function getAvatarLimit(): int
    {
        return $this->avatarLimit;
    }

    public function avatarTooltip(bool $show = true): static
    {
        $this->withAvatarTooltip = $show;

        return $this;
    }

    public function getWithAvatarTooltip(): bool
    {
        return $this->withAvatarTooltip;
    }

    public function avatarDecorator(Closure $callback): static
    {
        $this->avatarDecoratorCallback = $callback;

        return $this;
    }

    /** @param array<string, string> $labels */
    public function roleLabels(array $labels): static
    {
        $this->roleLabels = $labels;

        return $this;
    }

    public function getAvatarDecorator(): ?Closure
    {
        return $this->avatarDecoratorCallback;
    }

    /**
     * The assignments of a record: the ones the host already loaded, or the rows read here.
     *
     * @return Collection<int, WorkflowAssignment>
     */
    private function assignmentsOf(Model $record): Collection
    {
        if (! method_exists($record, 'assignments')) {
            return new Collection;
        }

        /** @var Collection<int, WorkflowAssignment> $assignments */
        $assignments = $record->relationLoaded('assignments')
            ? $record->getRelation('assignments')
            : $record->assignments()->with('user')->get();

        return $assignments;
    }

    /**
     * Get assigned users for the record.
     *
     * @return array<int, array{name: string, initials: string, assignment_type: string, roles: string}>
     */
    public function getAssignedUsers(?Model $record = null): array
    {
        $record ??= $this->getRecord();

        if (! $record || ! method_exists($record, 'assignments')) {
            return [];
        }

        // When the host eager-loaded the assignments (and their users), they are read from the
        // record: the column of a list is rendered once per row, and one query per row is how a
        // table of a hundred rows becomes two hundred queries.
        return $this->assignmentsOf($record)
            ->filter(fn (WorkflowAssignment $assignment): bool => $assignment->user !== null)
            ->map(function (WorkflowAssignment $assignment): array {
                /** @var Model $user */
                $user = $assignment->user;

                return UserSummary::of($user, $this->roleLabels) + [
                    'assignment_type' => $assignment->assignment_type,
                    'metadata' => $assignment->metadata,
                ];
            })
            ->values()
            ->all();
    }
}
