<?php

namespace RoBYCoNTe\FilamentFlow\Livewire;

use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Livewire component for managing workflow assignments with access overrides.
 *
 * Embed in a Filament form schema via:
 * ```php
 * \Filament\Schemas\Components\Livewire::make(AssignmentManager::class)
 *     ->visible(fn (?Model $record) => $record !== null),
 * ```
 *
 * The component receives the `$record` automatically from Filament's Livewire schema component.
 * It allows adding/removing user assignments and configuring per-assignment access overrides
 * (view, edit, transition) that bypass normal state-based access rules.
 */
class AssignmentManager extends Component implements HasForms
{
    use InteractsWithForms;

    #[Locked]
    public ?int $recordId = null;

    #[Locked]
    public ?string $recordType = null;

    public ?array $addFormData = [
        'selectedUserId' => null,
        'overrideView' => true,
        'overrideEdit' => false,
        'overrideTransition' => false,
    ];

    public bool $showAddForm = false;

    public function mount(?Model $record = null): void
    {
        if ($record) {
            $this->recordId = $record->getKey();
            $this->recordType = $record::class;
        }
    }

    public function getRecord(): ?Model
    {
        if (! $this->recordId || ! $this->recordType) {
            return null;
        }

        return $this->recordType::find($this->recordId);
    }

    public function addForm(Schema $form): Schema
    {
        return $form
            ->statePath('addFormData')
            ->schema([
                Select::make('selectedUserId')
                    ->label(__('filament-flow::messages.select_user'))
                    ->options(fn (): array => $this->getAvailableUsers())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),

                Fieldset::make(__('filament-flow::messages.access_overrides'))
                    ->schema([
                        Checkbox::make('overrideView')
                            ->label(__('filament-flow::messages.view'))
                            ->inline()
                            ->default(true),
                        Checkbox::make('overrideEdit')
                            ->label(__('filament-flow::messages.edit'))
                            ->inline(),
                        Checkbox::make('overrideTransition')
                            ->label(__('filament-flow::messages.transition'))
                            ->inline(),
                    ])
                    ->columns(3),
            ]);
    }

    public function getAssignments(): array
    {
        $record = $this->getRecord();

        if (! $record || ! method_exists($record, 'assignments')) {
            return [];
        }

        $userModel = $this->getUserModelClass();

        $query = $record->assignments()
            ->with(method_exists($userModel, 'roles') ? 'user.roles' : 'user');

        return $query
            ->get()
            ->filter(fn ($a) => $a->user !== null)
            ->map(function ($a) {
                $user = $a->user;
                $nameParts = explode(' ', trim($user->name));
                $initials = count($nameParts) >= 2
                    ? mb_strtoupper(mb_substr($nameParts[0], 0, 1).mb_substr(end($nameParts), 0, 1))
                    : mb_strtoupper(mb_substr($user->name, 0, 2));

                return [
                    'id' => $a->id,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'initials' => $initials,
                    'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->implode(', ') : '',
                    'assignment_type' => $a->assignment_type,
                    'override_view' => (bool) $a->override_view,
                    'override_edit' => (bool) $a->override_edit,
                    'override_transition' => (bool) $a->override_transition,
                    'has_overrides' => $a->hasAccessOverride(),
                ];
            })
            ->values()
            ->all();
    }

    public function getAvailableUsers(): array
    {
        $record = $this->getRecord();

        if (! $record) {
            return [];
        }

        $userModel = $this->getUserModelClass();

        $query = $userModel::query();

        $tenant = Filament::getTenant();
        if ($tenant) {
            $relationship = config('filament-flow.tenant_user_relationship', 'users');
            if (method_exists($tenant, $relationship)) {
                $query->whereIn('users.id', $tenant->{$relationship}()->pluck('users.id'));
            }
        }

        $assignedIds = method_exists($record, 'getAssignedUserIds')
            ? $record->getAssignedUserIds()
            : [];

        if (! empty($assignedIds)) {
            $query->whereNotIn('id', $assignedIds);
        }

        $users = method_exists($userModel, 'roles')
            ? $query->with('roles')->get()
            : $query->get();

        return $users
            ->mapWithKeys(function ($user): array {
                $label = $user->name;
                if (isset($user->roles) && $user->roles->isNotEmpty()) {
                    $label .= ' ('.$user->roles->pluck('name')->implode(', ').')';
                }

                return [$user->id => $label];
            })
            ->all();
    }

    public function canManageAssignments(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return (method_exists($user, 'isAdmin') && $user->isAdmin())
            || (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin());
    }

    public function toggleAddForm(): void
    {
        $this->showAddForm = ! $this->showAddForm;

        if ($this->showAddForm) {
            $this->addForm->fill([
                'selectedUserId' => null,
                'overrideView' => true,
                'overrideEdit' => false,
                'overrideTransition' => false,
            ]);
        }
    }

    public function addAssignment(): void
    {
        if (! $this->canManageAssignments()) {
            return;
        }

        $data = $this->addForm->getState();

        if (empty($data['selectedUserId'])) {
            return;
        }

        // View is required — at least view access must be granted
        if (! $data['overrideView']) {
            throw ValidationException::withMessages([
                'addFormData.overrideView' => [__('filament-flow::messages.view_override_required')],
            ]);
        }

        $record = $this->getRecord();

        if (! $record || ! method_exists($record, 'assignWithOverrides')) {
            return;
        }

        // Guard against race-condition duplicates (primary defense is the dropdown exclusion)
        if (method_exists($record, 'getAssignedUserIds') && in_array((int) $data['selectedUserId'], $record->getAssignedUserIds())) {
            throw ValidationException::withMessages([
                'addFormData.selectedUserId' => [__('filament-flow::messages.user_already_assigned')],
            ]);
        }

        $overrides = [
            'view' => $data['overrideView'] ? true : null,
            'edit' => $data['overrideEdit'] ? true : null,
            'transition' => $data['overrideTransition'] ? true : null,
        ];

        $record->assignWithOverrides(
            (int) $data['selectedUserId'],
            $overrides,
            'primary',
            auth()->user(),
        );

        $this->showAddForm = false;

        Notification::make()
            ->title(__('filament-flow::messages.assignment_saved'))
            ->success()
            ->send();
    }

    public function removeAssignment(int $assignmentId): void
    {
        if (! $this->canManageAssignments()) {
            return;
        }

        $record = $this->getRecord();

        if (! $record || ! method_exists($record, 'assignments')) {
            return;
        }

        $record->assignments()->where('id', $assignmentId)->delete();

        Notification::make()
            ->title(__('filament-flow::messages.assignment_removed'))
            ->success()
            ->send();
    }

    public function toggleOverride(int $assignmentId, string $type): void
    {
        if (! $this->canManageAssignments()) {
            return;
        }

        $record = $this->getRecord();

        if (! $record || ! method_exists($record, 'assignments')) {
            return;
        }

        $column = 'override_'.$type;
        $assignment = $record->assignments()->where('id', $assignmentId)->first();

        if (! $assignment) {
            return;
        }

        $assignment->update([
            $column => $assignment->{$column} ? null : true,
        ]);
    }

    private function getUserModelClass(): string
    {
        return config('filament-flow.user_model')
            ?? config('auth.providers.users.model', 'App\\Models\\User');
    }

    public function render(): View
    {
        return view('filament-flow::livewire.assignment-manager', [
            'assignments' => $this->getAssignments(),
            'canManage' => $this->canManageAssignments(),
        ]);
    }
}
