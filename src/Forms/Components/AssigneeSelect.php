<?php

namespace RoBYCoNTe\FilamentFlow\Forms\Components;

use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Support\Collection;
use RoBYCoNTe\FilamentFlow\Support\RoleLabel;
use RoBYCoNTe\FilamentFlow\Support\UserModel;

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

    /**
     * The words the host uses for its own roles, keyed by role name: a name is a key, and a
     * list of people reads like a database without them.
     *
     * @var array<string, string>
     */
    protected array $roleLabels = [];

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

    /** @param array<string, string> $labels */
    public function roleLabels(array $labels): static
    {
        $this->roleLabels = $labels;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveAvailableUsers(): array
    {
        $userModel = UserModel::resolve();

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
            $label = (string) $user->getAttribute('name');
            $roles = $user->getAttribute('roles');

            if ($roles instanceof Collection && $roles->isNotEmpty()) {
                // The role names are keys: the label reads in the words of the host.
                $label .= ' ('.$roles
                    ->map(fn ($role): string => RoleLabel::for((string) $role->name, $this->roleLabels))
                    ->implode(', ').')';
            }
            $options[$user->getKey()] = $label;
        }

        return $options;
    }
}
