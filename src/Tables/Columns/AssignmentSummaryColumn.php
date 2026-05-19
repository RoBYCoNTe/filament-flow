<?php

namespace RoBYCoNTe\FilamentFlow\Tables\Columns;

use Closure;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;

class AssignmentSummaryColumn extends Column
{
    protected string $view = 'filament-flow::tables.columns.assignment-summary-column';

    protected int $avatarLimit = 3;

    protected bool $withAvatarTooltip = true;

    protected ?Closure $avatarDecoratorCallback = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disableClick();
    }

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

    public function getAvatarDecorator(): ?Closure
    {
        return $this->avatarDecoratorCallback;
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

        return $record->assignments()
            ->with('user')
            ->get()
            ->filter(fn ($assignment) => $assignment->user !== null)
            ->map(function ($assignment) {
                $user = $assignment->user;
                $nameParts = explode(' ', trim($user->name));
                $initials = count($nameParts) >= 2
                    ? mb_strtoupper(mb_substr($nameParts[0], 0, 1).mb_substr(end($nameParts), 0, 1))
                    : mb_strtoupper(mb_substr($user->name, 0, 2));

                $roles = method_exists($user, 'getRoleNames')
                    ? $user->getRoleNames()->implode(', ')
                    : '';

                return [
                    'name' => $user->name,
                    'initials' => $initials,
                    'assignment_type' => $assignment->assignment_type,
                    'roles' => $roles,
                    'metadata' => $assignment->metadata,
                ];
            })
            ->values()
            ->all();
    }
}
