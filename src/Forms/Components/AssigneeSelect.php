<?php

namespace RoBYCoNTe\FilamentFlow\Forms\Components;

use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;

/**
 * A select component for assigning users in workflow transitions.
 *
 * Provides a multi-select, searchable, preloaded dropdown of users
 * with support for multi-tenancy and assignment type configuration.
 *
 * @method static static make(string $name)
 */
class AssigneeSelect extends Select
{
    protected string $assignmentType = 'primary';

    protected ?Closure $usersQueryModifier = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->multiple()
            ->searchable()
            ->preload()
            ->options(fn (): array => $this->resolveAvailableUsers())
            ->afterStateHydrated(function (AssigneeSelect $component, $record): void {
                if ($record && method_exists($record, 'getAssignedUserIds')) {
                    $component->state($record->getAssignedUserIds([$this->assignmentType]));
                }
            })
            ->dehydrateStateUsing(fn ($state) => $state);
    }

    public function assignmentType(string $type): static
    {
        $this->assignmentType = $type;

        return $this;
    }

    public function getAssignmentType(): string
    {
        return $this->assignmentType;
    }

    public function usersQuery(Closure $modifier): static
    {
        $this->usersQueryModifier = $modifier;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveAvailableUsers(): array
    {
        $userModel = config('filament-flow.user_model')
            ?? config('auth.providers.users.model', 'App\\Models\\User');

        $query = $userModel::query();

        // Filter by tenant if in Filament multi-tenant context
        $tenant = Filament::getTenant();
        if ($tenant) {
            $relationship = config('filament-flow.tenant_user_relationship', 'users');
            if (method_exists($tenant, $relationship)) {
                $query->whereIn('users.id', $tenant->{$relationship}()->pluck('users.id'));
            }
        }

        // Apply custom query modifier
        if ($this->usersQueryModifier) {
            ($this->usersQueryModifier)($query);
        }

        // Eager load roles if available, to display "Name (Role)" labels
        $users = method_exists($userModel, 'roles')
            ? $query->with('roles')->get()
            : $query->get();

        $options = [];
        foreach ($users as $user) {
            $label = $user->name;
            if (isset($user->roles) && $user->roles->isNotEmpty()) {
                $label .= ' ('.$user->roles->pluck('name')->implode(', ').')';
            }
            $options[$user->id] = $label;
        }

        return $options;
    }
}
