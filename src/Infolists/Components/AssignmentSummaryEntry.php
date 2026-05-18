<?php

namespace RoBYCoNTe\FilamentFlow\Infolists\Components;

use Filament\Infolists\Components\Entry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService;

class AssignmentSummaryEntry extends Entry
{
    protected string $view = 'filament-flow::infolists.assignment-summary';

    protected string $stateColumn = 'state';

    public static function make(?string $name = 'assignment-summary'): static
    {
        return parent::make($name);
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

    /**
     * Get assigned users with their effective permissions for the current state.
     *
     * @return Collection<int, array{user: Model, assignment_type: string, can_view: bool, can_edit: bool, can_transition: bool}>
     */
    public function getAssignedUsersWithPermissions(): Collection
    {
        $record = $this->getRecord();

        if (! $record instanceof Model || ! method_exists($record, 'assignments')) {
            return collect();
        }

        $accessService = app(WorkflowStateAccessService::class);

        return $record->assignments()
            ->with('user')
            ->get()
            ->filter(fn ($assignment) => $assignment->user !== null)
            ->map(fn ($assignment) => [
                'user' => $assignment->user,
                'assignment_type' => $assignment->assignment_type,
                'can_view' => $accessService->canView($record, $assignment->user),
                'can_edit' => $accessService->canEdit($record, $assignment->user),
                'can_transition' => $accessService->canTransition($record, $assignment->user),
                'override_view' => $assignment->override_view === true,
                'override_edit' => $assignment->override_edit === true,
                'override_transition' => $assignment->override_transition === true,
                'has_overrides' => $assignment->hasAccessOverride(),
            ])
            ->values();
    }

    /**
     * Get role names that have access to the record in its current state,
     * excluding @assigned (shown separately as individual users).
     *
     * @return array{view: array<string>, edit: array<string>, transition: array<string>}
     */
    public function getRoleAccess(): array
    {
        $record = $this->getRecord();

        if (! $record instanceof Model) {
            return ['view' => [], 'edit' => [], 'transition' => []];
        }

        $workflow = Workflow::findForModel(get_class($record), $this->stateColumn);
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
     * Check if the current user is a super admin.
     */
    public function isCurrentUserSuperAdmin(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        $superAdminRoles = config('filament-flow.state_access.super_admin_roles', ['super_admin']);

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($superAdminRoles);
        }

        return false;
    }
}
