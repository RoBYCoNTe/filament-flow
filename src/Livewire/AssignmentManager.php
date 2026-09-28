<?php

namespace RoBYCoNTe\FilamentFlow\Livewire;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RoBYCoNTe\FilamentFlow\Services\OwnershipTransfer;
use RoBYCoNTe\FilamentFlow\Support\AssignmentTypeConfig;
use RoBYCoNTe\FilamentFlow\Support\OwnershipHistory;
use RoBYCoNTe\FilamentFlow\Support\RecordOwner;
use RoBYCoNTe\FilamentFlow\Support\RoleLabel;
use RoBYCoNTe\FilamentFlow\Support\UserModel;
use RoBYCoNTe\FilamentFlow\Support\UserSummary;

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
 *
 * It also owns the ownership of the record: who holds it now, and handing it over — with a
 * choice of what the previous owner keeps, nothing or a role that lets them still see it.
 *
 * The panel is a thing for administrators; pass `superAdminOnly` to keep it out of the
 * hands of plain admins as well.
 */
class AssignmentManager extends Component implements HasForms
{
    use InteractsWithForms;

    /** How an override reads, as the form carries it: the value stored on the row. */
    public const FOLLOW = 'follow';

    public const GRANT = 'grant';

    public const DENY = 'deny';

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

    /**
     * How the roles of the assigned people read: the words of the host, keyed by role name.
     * A name is a key, and a panel that shows it raw reads like a database.
     *
     * @var array<string, string>
     */
    #[Locked]
    public array $roleLabels = [];

    /**
     * When true, only a super administrator manages the panel: plain admins read it, and
     * nothing more. The default keeps the historical behaviour (admins manage).
     */
    #[Locked]
    public bool $superAdminOnly = false;

    /**
     * Whether the panel opens by saying what it is for: who holds the record, who works on
     * it, and what each permission means. A tool nobody understands is a tool misused.
     */
    #[Locked]
    public bool $showExplanation = true;

    /**
     * Whether the panel keeps the handovers of the record — collapsed — where they can be read
     * in full: the owner column tells the story in one line, this tells it all.
     */
    #[Locked]
    public bool $showHistory = true;

    public ?array $addFormData = [
        'selectedUserId' => null,
        'assignmentType' => 'primary',
        'overrideView' => self::GRANT,
        'overrideEdit' => self::FOLLOW,
        'overrideTransition' => self::FOLLOW,
    ];

    public ?array $transferFormData = [
        'newOwnerId' => null,
        'retention' => 'none',
        'note' => null,
    ];

    public bool $showAddForm = false;

    public bool $showTransferForm = false;

    /**
     * The record may arrive in several shapes, because it comes from where the component is
     * embedded: a Filament schema hands over a model that has been through the wire and come
     * back as an array, a Blade mount hands over the model itself, and a host may simply know
     * the id. All of them are accepted; what cannot be understood is ignored.
     *
     * @param  Model|array<string,mixed>|int|string|null  $record
     */
    public function mount(Model|array|int|string|null $record = null, ?string $recordType = null, array $roleLabels = [], bool $superAdminOnly = false, bool $showExplanation = true, bool $showHistory = true): void
    {
        $this->roleLabels = $roleLabels;
        $this->superAdminOnly = $superAdminOnly;
        $this->showExplanation = $showExplanation;
        $this->showHistory = $showHistory;

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

    /** @return array<string, array{value: ?bool, label: string}> */
    public static function overrideChoices(): array
    {
        return [
            self::FOLLOW => ['value' => null, 'label' => __('filament-flow::messages.override_follow')],
            self::GRANT => ['value' => true, 'label' => __('filament-flow::messages.override_grant')],
            self::DENY => ['value' => false, 'label' => __('filament-flow::messages.override_deny')],
        ];
    }

    public static function overrideValue(string $choice): ?bool
    {
        return self::overrideChoices()[$choice]['value'] ?? null;
    }

    public function addForm(Schema $form): Schema
    {
        return $form
            ->statePath('addFormData')
            ->schema([
                // Who, and with which role: two distinct things, asked one at a time.
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

                // And what they may do: each permission has three readings — the call decides,
                // the person is allowed whatever the state says, or the person is shut out
                // even where the state would open the door.
                Fieldset::make(__('filament-flow::messages.access_overrides'))
                    ->schema([
                        $this->overrideToggle('overrideView', __('filament-flow::messages.view'), self::GRANT, __('filament-flow::messages.help_override_view')),
                        $this->overrideToggle('overrideEdit', __('filament-flow::messages.edit'), self::FOLLOW, __('filament-flow::messages.help_override_edit')),
                        $this->overrideToggle('overrideTransition', __('filament-flow::messages.transition'), self::FOLLOW, __('filament-flow::messages.help_override_transition')),
                    ]),
            ]);
    }

    /** One three-way permission: the call decides, allowed, or shut out. */
    private function overrideToggle(string $name, string $label, string $default, string $help): ToggleButtons
    {
        return ToggleButtons::make($name)
            ->label($label)
            ->helperText($help)
            ->options([
                self::FOLLOW => __('filament-flow::messages.override_follow'),
                self::GRANT => __('filament-flow::messages.override_grant'),
                self::DENY => __('filament-flow::messages.override_deny'),
            ])
            ->colors([
                self::FOLLOW => 'gray',
                self::GRANT => 'success',
                self::DENY => 'danger',
            ])
            ->icons([
                self::FOLLOW => 'heroicon-m-adjustments-horizontal',
                self::GRANT => 'heroicon-m-check',
                self::DENY => 'heroicon-m-x-mark',
            ])
            ->inline()
            ->default($default)
            ->required();
    }

    public function transferForm(Schema $form): Schema
    {
        return $form
            ->statePath('transferFormData')
            ->schema([
                Select::make('newOwnerId')
                    ->label(__('filament-flow::messages.new_owner'))
                    ->helperText(__('filament-flow::messages.help_new_owner'))
                    ->options(fn (): array => $this->getTransferCandidates())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),

                ToggleButtons::make('retention')
                    ->label(__('filament-flow::messages.old_owner_retention'))
                    ->helperText(__('filament-flow::messages.help_old_owner_retention'))
                    ->options([
                        'none' => __('filament-flow::messages.retention_none'),
                        'viewer' => __('filament-flow::messages.retention_viewer'),
                        'secondary' => __('filament-flow::messages.retention_secondary'),
                    ])
                    ->colors([
                        'none' => 'gray',
                        'viewer' => 'info',
                        'secondary' => 'warning',
                    ])
                    ->inline()
                    ->default('none')
                    ->required()
                    ->live(),

                TextInput::make('note')
                    ->label(__('filament-flow::messages.transfer_note'))
                    ->helperText(__('filament-flow::messages.help_transfer_note'))
                    ->maxLength(255),
            ]);
    }

    /** @return array<int, string> */
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

                $overrides = [$a->override_view, $a->override_edit, $a->override_transition];

                // `id` is the row of the assignment — it is what the buttons of the panel act
                // on — while `user_id` is the person. The two are not the same thing, and the
                // panel needs both.
                return [
                    ...UserSummary::of($user, $this->roleLabels),
                    'id' => $a->id,
                    'user_id' => $user->getKey(),
                    'assignment_type' => $a->assignment_type,
                    'override_view' => $a->override_view,
                    'override_edit' => $a->override_edit,
                    'override_transition' => $a->override_transition,
                    'has_overrides' => $a->hasAccessOverride(),
                    'has_denial' => in_array(false, $overrides, true),
                    'has_explicit_access' => in_array(null, $overrides, true) === false,
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

        $query = $this->baseUserQuery();

        $assignedIds = method_exists($record, 'getAssignedUserIds')
            ? $record->getAssignedUserIds()
            : [];

        if (! empty($assignedIds)) {
            $query->whereNotIn('id', $assignedIds);
        }

        return $this->mapUserOptions($query);
    }

    /** Everybody who may take the record over: the current owner is not on the list. */
    public function getTransferCandidates(): array
    {
        $record = $this->getRecord();

        if (! $record) {
            return [];
        }

        $query = $this->baseUserQuery();

        $currentOwnerId = RecordOwner::id($record);

        if ($currentOwnerId !== null) {
            $query->where('id', '!=', $currentOwnerId);
        }

        return $this->mapUserOptions($query);
    }

    /** The user who owns the record today, with the words the host uses for them. */
    public function getCurrentOwner(): ?array
    {
        $record = $this->getRecord();

        if (! $record || RecordOwner::id($record) === null) {
            return null;
        }

        /** @var Model|null $user */
        $user = RecordOwner::of($record);

        if ($user === null) {
            return null;
        }

        return UserSummary::of($user, $this->roleLabels);
    }

    public function canManageAssignments(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($this->superAdminOnly) {
            return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
        }

        return (method_exists($user, 'isAdmin') && $user->isAdmin())
            || (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin());
    }

    /**
     * The explanation is read once: it stays folded for whoever acts, and opens for whoever
     * may not — there it is the whole content of the panel, and it tells them why the
     * controls are not theirs.
     */
    public function explanationStartsCollapsed(): bool
    {
        return $this->canManageAssignments();
    }

    public function toggleAddForm(): void
    {
        $this->showAddForm = ! $this->showAddForm;

        if ($this->showAddForm) {
            $this->getSchema('addForm')?->fill([
                'selectedUserId' => null,
                'assignmentType' => 'primary',
                'overrideView' => self::GRANT,
                'overrideEdit' => self::FOLLOW,
                'overrideTransition' => self::FOLLOW,
            ]);
        }
    }

    public function toggleTransferForm(): void
    {
        $this->showTransferForm = ! $this->showTransferForm;

        if ($this->showTransferForm) {
            $this->getSchema('transferForm')?->fill([
                'newOwnerId' => null,
                'retention' => 'none',
                'note' => null,
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

        // Each permission is read the way the toggle said: the call decides, allowed, or
        // shut out. All three on "the call decides" is a legitimate answer — the type of
        // the assignment still matters to the rules that ask for an assignee.
        $record->assignWithOverrides(
            (int) $data['selectedUserId'],
            [
                'view' => self::overrideValue($data['overrideView'] ?? self::FOLLOW),
                'edit' => self::overrideValue($data['overrideEdit'] ?? self::FOLLOW),
                'transition' => self::overrideValue($data['overrideTransition'] ?? self::FOLLOW),
            ],
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

    /** One override cycles through its three readings: the call decides, allowed, shut out. */
    public function toggleOverride(int $assignmentId, string $type): void
    {
        if (! $this->canManageAssignments()) {
            return;
        }

        if (! in_array($type, ['view', 'edit', 'transition'], true)) {
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
            $column => match ($assignment->{$column}) {
                null => true,
                true => false,
                default => null,
            },
        ]);
    }

    /**
     * Hands the record over: the person who holds it changes, and what the previous one
     * keeps — nothing, the eyes of an observer, the hands of a collaborator — is written
     * beside the change, so the history can be read without guessing.
     */
    public function transferOwnership(): void
    {
        if (! $this->canManageAssignments()) {
            return;
        }

        $record = $this->getRecord();

        if (! $record || ! RecordOwner::exists($record)) {
            return;
        }

        $data = $this->getSchema('transferForm')?->getState() ?? [];

        if (empty($data['newOwnerId'])) {
            throw ValidationException::withMessages([
                'transferFormData.newOwnerId' => [__('filament-flow::messages.new_owner_required')],
            ]);
        }

        $toUserId = (int) $data['newOwnerId'];

        if ((RecordOwner::id($record) === null ? null : (int) RecordOwner::id($record)) === $toUserId) {
            throw ValidationException::withMessages([
                'transferFormData.newOwnerId' => [__('filament-flow::messages.new_owner_is_current')],
            ]);
        }

        // The handover itself — the new owner, what the previous one keeps, the record of it,
        // the event — is the work of the engine, not of this form.
        app(OwnershipTransfer::class)->transfer(
            record: $record,
            toUserId: $toUserId,
            retention: (string) ($data['retention'] ?? OwnershipTransfer::RETENTION_NONE),
            note: filled($data['note'] ?? null) ? (string) $data['note'] : null,
            actor: auth()->user(),
        );

        $this->showTransferForm = false;
        $this->transferFormData = ['newOwnerId' => null, 'retention' => 'none', 'note' => null];

        Notification::make()
            ->title(__('filament-flow::messages.ownership_transferred'))
            ->success()
            ->send();
    }

    /** The users of the tenant, when there is one; everybody otherwise. */
    private function baseUserQuery(): Builder
    {
        $userModel = $this->getUserModelClass();

        $query = $userModel::query();

        $tenant = Filament::getTenant();
        if ($tenant) {
            $relationship = config('filament-flow.tenant_user_relationship', 'users');
            if (method_exists($tenant, $relationship)) {
                $query->whereIn('users.id', $tenant->{$relationship}()->pluck('users.id'));
            }
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    private function mapUserOptions(Builder $query): array
    {
        $userModel = $this->getUserModelClass();

        $users = method_exists($userModel, 'roles')
            ? $query->with('roles')->get()
            : $query->get();

        return $users
            ->mapWithKeys(function ($user): array {
                $label = $user->getAttribute('name');

                if (isset($user->roles) && $user->roles->isNotEmpty()) {
                    // A role name is a key: the person picking a colleague reads the words of
                    // the office, not the names of the roles as they are stored.
                    $label .= ' ('.$user->roles
                        ->map(fn ($role): string => RoleLabel::for((string) $role->name, $this->roleLabels))
                        ->implode(', ').')';
                }

                return [$user->getKey() => $label];
            })
            ->all();
    }

    private function getUserModelClass(): string
    {
        return UserModel::resolve();
    }

    public function render(): View
    {
        /** @var view-string $view */
        $view = 'filament-flow::livewire.assignment-manager';

        $record = $this->getRecord();

        return view($view, [
            'assignments' => $this->getAssignments(),
            'currentOwner' => $this->getCurrentOwner(),
            'canManage' => $this->canManageAssignments(),
            'typeConfig' => AssignmentTypeConfig::all(),
            'ownershipHistory' => $record !== null ? OwnershipHistory::for($record) : [],
            'hasOwnerField' => $record !== null && RecordOwner::exists($record),
        ]);
    }
}
