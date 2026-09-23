<?php

namespace RoBYCoNTe\FilamentFlow\Livewire;

use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RoBYCoNTe\FilamentFlow\Support\AssignmentTypeConfig;
use RoBYCoNTe\FilamentFlow\Support\UserModel;

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

    /**
     * The key of the record, which is not always a number: hosts that use ULIDs or UUIDs
     * have string keys, and casting one to int points at a record that does not exist.
     */
    #[Locked]
    public int|string|null $recordId = null;

    #[Locked]
    public ?string $recordType = null;

    #[Locked]
    public ?string $assignmentBadgesView = null;

    public ?array $addFormData = [
        'selectedUserId' => null,
        'assignmentType' => 'primary',
        'overrideView' => true,
        'overrideEdit' => false,
        'overrideTransition' => false,
    ];

    public bool $showAddForm = false;

    /**
     * The record may arrive in several shapes, because it comes from where the component is
     * embedded: a Filament schema hands over a model that has been through the wire and come
     * back as an array, a Blade mount hands over the model itself, and a host may simply know
     * the id. All of them are accepted; what cannot be understood is ignored.
     *
     * @param  Model|array<string,mixed>|int|string|null  $record
     */
    public function mount(Model|array|int|string|null $record = null, ?string $recordType = null): void
    {
        if ($record instanceof Model) {
            $this->recordId = $record->getKey();
            $this->recordType = $record::class;

            return;
        }

        if (is_array($record)) {
            $this->recordId = $record['id'] ?? null;
            $this->recordType = $recordType ?? (isset($record['class']) ? (string) $record['class'] : null);

            return;
        }

        if ($record !== null && $record !== '') {
            $this->recordId = $record;
            $this->recordType = $recordType;
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
                // Chi, e con che ruolo: due cose distinte, dette una per volta.
                Grid::make(2)->schema([
                    Select::make('selectedUserId')
                        ->label(__('filament-flow::messages.select_user'))
                        ->helperText(__('filament-flow::messages.help_select_user'))
                        ->options(fn (): array => $this->getAvailableUsers())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),

                    Select::make('assignmentType')
                        ->label(__('filament-flow::messages.assignment_type_label'))
                        ->helperText(__('filament-flow::messages.help_assignment_type'))
                        ->options(AssignmentTypeConfig::options())
                        ->default('primary')
                        ->required(),
                ]),

                // And what they may do: permissions that override the rules of the call.
                // The explanation of the permissions lives in the panel, above the form: the
                // Fieldset of Filament only takes the label.
                Fieldset::make(__('filament-flow::messages.access_overrides'))
                    ->schema([
                        Checkbox::make('overrideView')
                            ->label(__('filament-flow::messages.view'))
                            ->helperText(__('filament-flow::messages.help_override_view')),
                        Checkbox::make('overrideEdit')
                            ->label(__('filament-flow::messages.edit'))
                            ->helperText(__('filament-flow::messages.help_override_edit')),
                        Checkbox::make('overrideTransition')
                            ->label(__('filament-flow::messages.transition'))
                            ->helperText(__('filament-flow::messages.help_override_transition')),
                    ])
                    // The three checkboxes on one line: the permissions are read together, and
                    // the panel stays as tall as it needs to be.
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
                    'metadata' => $a->metadata,
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
            $this->getSchema('addForm')?->fill([
                'selectedUserId' => null,
                'assignmentType' => 'primary',
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

        $data = $this->getSchema('addForm')?->getState() ?? [];

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

        // View access is mandatory for an assignment; edit/transition are optional.
        $overrides = [
            'view' => true,
            'edit' => $data['overrideEdit'] ? true : null,
            'transition' => $data['overrideTransition'] ? true : null,
        ];

        $record->assignWithOverrides(
            (int) $data['selectedUserId'],
            $overrides,
            $data['assignmentType'] ?? 'primary',
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

    public function changeAssignmentType(int $assignmentId, string $newType): void
    {
        if (! $this->canManageAssignments()) {
            return;
        }

        $record = $this->getRecord();

        if (! $record || ! method_exists($record, 'changeAssignmentType')) {
            return;
        }

        $changed = $record->changeAssignmentType($assignmentId, $newType);

        if (! $changed) {
            Notification::make()
                ->title(__('filament-flow::messages.change_type_conflict'))
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('filament-flow::messages.assignment_type_changed'))
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
        return UserModel::resolve();
    }

    public function render(): View
    {
        /** @var view-string $view */
        $view = 'filament-flow::livewire.assignment-manager';

        return view($view, [
            'assignments' => $this->getAssignments(),
            'canManage' => $this->canManageAssignments(),
            'typeConfig' => AssignmentTypeConfig::all(),
        ]);
    }
}
