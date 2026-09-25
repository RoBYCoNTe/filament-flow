<?php

namespace RoBYCoNTe\FilamentFlow\Infolists\Components;

use Closure;
use Filament\Infolists\Components\Entry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Services\StateService;
use RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService;
use RoBYCoNTe\FilamentFlow\Support\AccessRuleEvaluator;
use RoBYCoNTe\FilamentFlow\Support\AssignmentTypeConfig;
use RoBYCoNTe\FilamentFlow\Support\LocalizedDate;
use RoBYCoNTe\FilamentFlow\Support\RoleLabel;

/**
 * The entry that shows who holds a record: the people assigned, the kind of assignment each one
 * carries, and what they are allowed to do — all of it read from the engine rather than from
 * the record.
 *
 * The tenant of the row has to be passed: without it the entry reads the workflow of somebody
 * else, and the permissions it shows are the wrong ones.
 */
class AssignmentSummaryEntry extends Entry
{
    protected string $view = 'filament-flow::infolists.assignment-summary';

    protected string $stateColumn = 'state';

    protected ?Closure $metadataBadgesCallback = null;

    /**
     * The words the host uses for its own roles: a name (`super_admin`) is a key, not a
     * sentence, and only the host knows how to read it.
     *
     * @var array<string, string>
     */
    protected array $roleLabels = [];

    protected ?Closure $roleLabelCallback = null;

    public static function make(?string $name = 'assignment-summary'): static
    {
        // A name humanised by the framework ("Flow assignment summary") says nothing: the
        // label starts translated, and the host may still name it as it likes.
        return parent::make($name)
            ->label(__('filament-flow::messages.assignment_summary_label'));
    }

    public function metadataBadges(Closure $callback): static
    {
        $this->metadataBadgesCallback = $callback;

        return $this;
    }

    /**
     * How the roles of the assigned people read: a map of names to labels, or a callback that
     * answers one name at a time.
     *
     * @param  Closure(string): ?string|array<string, string>  $labels
     */
    public function roleLabels(Closure|array $labels): static
    {
        if ($labels instanceof Closure) {
            $this->roleLabelCallback = $labels;
        } else {
            $this->roleLabels = $labels;
        }

        return $this;
    }

    /**
     * The label of a role: what the host says, then the words of the package, then the name
     * read as it is written — never the raw key, when something better exists.
     */
    public function getRoleLabel(string $role): string
    {
        if ($this->roleLabelCallback !== null) {
            $label = ($this->roleLabelCallback)($role);

            if (is_string($label) && trim($label) !== '') {
                return $label;
            }
        }

        return RoleLabel::for($role, $this->roleLabels);
    }

    public function stateColumn(string $column): static
    {
        $this->stateColumn = $column;

        return $this;
    }

    public function getStateColumn(): string
    {
        return $this->stateColumn;
    }

    /** The same rule the timeline follows: the locale decides, unless the host says otherwise. */
    public function getDateTimeFormat(): string
    {
        return LocalizedDate::dateTime();
    }

    /**
     * Get assigned users with their effective permissions for the current state.
     *
     * @return Collection<int, array{user: Model, assignment_type: string, roles: list<string>,
     * assigned_at: mixed, assigned_by: string|null, can_view: bool, can_edit: bool,
     * can_transition: bool, override_view: bool, override_edit: bool, override_transition: bool,
     * has_overrides: bool, metadata: mixed, metadata_badges: array}>
     */
    public function getAssignedUsersWithPermissions(): Collection
    {
        $record = $this->record();

        if ($record === null || ! method_exists($record, 'assignments')) {
            return collect();
        }

        $accessService = app(WorkflowStateAccessService::class);

        return $record->assignments()
            ->with(['user', 'assignedBy'])
            ->get()
            ->filter(fn ($assignment) => $assignment->user !== null)
            ->map(function ($assignment) use ($accessService, $record) {
                $user = $assignment->user;

                $data = [
                    'user' => $user,
                    'assignment_type' => $assignment->assignment_type,
                    // The words the host uses for the roles of this person, not the names of
                    // the roles as they are stored.
                    'roles' => method_exists($user, 'getRoleNames')
                        ? $user->getRoleNames()->map(fn ($role): string => $this->getRoleLabel((string) $role))->values()->all()
                        : [],
                    'assigned_at' => $assignment->assigned_at,
                    'assigned_by' => $assignment->assignedBy?->name,
                    'can_view' => $accessService->canView($record, $user),
                    'can_edit' => $accessService->canEdit($record, $user),
                    'can_transition' => $accessService->canTransition($record, $user),
                    'override_view' => $assignment->override_view === true,
                    'override_edit' => $assignment->override_edit === true,
                    'override_transition' => $assignment->override_transition === true,
                    'has_overrides' => $assignment->hasAccessOverride(),
                    'metadata' => $assignment->metadata,
                ];

                $data['metadata_badges'] = $this->metadataBadgesCallback
                    ? ($this->metadataBadgesCallback)($data)
                    : [];

                return $data;
            })
            // Who holds the case first, then who helps, then who watches.
            ->sortBy(static fn (array $row): int => (int) array_search($row['assignment_type'], ['primary', 'secondary', 'viewer'], true))
            ->values();
    }

    /**
     * The state the permissions are read in: they change with it, so a summary that does not
     * say which state it describes says something else.
     */
    public function getStateLabel(): ?string
    {
        $record = $this->record();

        if ($record === null) {
            return null;
        }

        $state = $record->{$this->stateColumn};

        if ($state instanceof \Stringable) {
            $state = (string) $state;
        }

        if (! is_string($state) || $state === '') {
            return null;
        }

        $metadata = app(StateService::class)->getStateMetadata(
            get_class($record),
            $state,
            $this->stateColumn,
            method_exists($record, 'getWorkflowTenantId') ? $record->getWorkflowTenantId() : null,
        );

        $label = $metadata['label'] ?? null;

        return is_string($label) && trim($label) !== '' ? $label : null;
    }

    /**
     * Get role names that have access to the record in its current state,
     * excluding @assigned (shown separately as individual users).
     *
     * @return array{view: array<string>, edit: array<string>, transition: array<string>}
     */
    public function getRoleAccess(): array
    {
        $record = $this->record();

        if ($record === null) {
            return ['view' => [], 'edit' => [], 'transition' => []];
        }

        // The tenant of the row: with one workflow per owner, without it one reads the workflow
        // of somebody else — and the permissions shown would be the wrong ones.
        $workflow = Workflow::findForModel(
            get_class($record),
            $this->stateColumn,
            method_exists($record, 'getWorkflowTenantId') ? $record->getWorkflowTenantId() : null,
        );
        if (! $workflow) {
            return ['view' => [], 'edit' => [], 'transition' => []];
        }

        $currentState = $record->{$this->stateColumn};
        $state = $workflow->states()
            ->where(function ($query) use ($currentState) {
                $query->where('class_name', $currentState)
                    ->orWhere('name', $currentState);
            })
            ->first();

        if (! $state) {
            return ['view' => [], 'edit' => [], 'transition' => []];
        }

        $result = [];

        foreach (['view', 'edit', 'transition'] as $accessType) {
            $rules = WorkflowStateAccessRule::where('state_id', $state->id)
                ->where('access_type', $accessType)
                ->where('is_active', true)
                ->pluck('rule')
                ->toArray();

            $roles = [];
            foreach ($rules as $rule) {
                if (str_starts_with($rule, 'role:')) {
                    $roleNames = explode(',', substr($rule, 5));
                    $roles = array_merge($roles, $roleNames);
                }
            }

            $result[$accessType] = array_unique($roles);
        }

        return $result;
    }

    /**
     * The record this entry reads, when there is one: an entry asked outside a schema has no
     * container to take it from, and answers nothing instead of failing.
     */
    protected function record(): ?Model
    {
        try {
            $record = $this->getRecord();
        } catch (\Throwable) {
            return null;
        }

        return $record instanceof Model ? $record : null;
    }

    /**
     * Check if the current user is a super admin.
     */
    /**
     * @return array<string, array{label: string, bg: string, icon: string}>
     */
    public function getTypeConfig(): array
    {
        return AssignmentTypeConfig::all();
    }

    public function isCurrentUserSuperAdmin(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Same resolver as the access rules (see TransitionTimeline).
        return app(AccessRuleEvaluator::class)->isSuperAdmin($user);
    }
}
