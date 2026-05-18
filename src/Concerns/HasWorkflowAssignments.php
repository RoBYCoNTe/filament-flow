<?php

/** @noinspection PhpPossiblePolymorphicInvocationInspection */

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use RoBYCoNTe\FilamentFlow\Events\WorkflowAssigned;
use RoBYCoNTe\FilamentFlow\Models\WorkflowAssignment;

trait HasWorkflowAssignments
{
    public function assignments(): MorphMany
    {
        return $this->morphMany(WorkflowAssignment::class, 'assignable');
    }

    public function isAssignedTo(int|Model $user, ?string $type = null): bool
    {
        $query = $this->assignments()->where('user_id', $this->resolveUserId($user));

        if ($type !== null) {
            $query->where('assignment_type', $type);
        }

        return $query->exists();
    }

    /**
     * @noinspection PhpIncompatibleReturnTypeInspection
     */
    public function assignTo(int|Model $user, string $type = 'primary', ?Model $assignedBy = null): WorkflowAssignment
    {
        $userId = $this->resolveUserId($user);

        $existing = $this->assignments()
            ->where('user_id', $userId)
            ->where('assignment_type', $type)
            ->first();

        if ($existing) {
            return $existing;
        }

        /** @noinspection LaravelEloquentGuardedAttributeAssignmentInspection */
        $assignment = $this->assignments()->create([
            'user_id' => $userId,
            'assignment_type' => $type,
            'assigned_by' => $assignedBy?->id ?? auth()->id(),
        ]);

        $assigneeModel = $user instanceof Model ? $user : $assignment->user;
        WorkflowAssigned::dispatch($this, $assigneeModel, $assignedBy, $type);

        return $assignment;
    }

    public function unassignFrom(int|Model $user, ?string $type = null): int
    {
        $query = $this->assignments()->where('user_id', $this->resolveUserId($user));

        if ($type !== null) {
            $query->where('assignment_type', $type);
        }

        return $query->delete();
    }

    public function getAssignedUsers(?array $types = null): Collection
    {
        $query = $this->assignments()->with('user');

        if ($types !== null) {
            $query->whereIn('assignment_type', $types);
        }

        return $query->get()->pluck('user');
    }

    public function getAssignedUserIds(?array $types = null): array
    {
        $query = $this->assignments();

        if ($types !== null) {
            $query->whereIn('assignment_type', $types);
        }

        return $query->pluck('user_id')->toArray();
    }

    public function getPrimaryAssignedUsers(): Collection
    {
        return $this->getAssignedUsers(['primary']);
    }

    public function getSecondaryAssignedUsers(): Collection
    {
        return $this->getAssignedUsers(['secondary']);
    }

    public function getViewerAssignedUsers(): Collection
    {
        return $this->getAssignedUsers(['viewer']);
    }

    public function reassign(int|Model $fromUser, int|Model $toUser, ?string $type = null): int
    {
        $query = $this->assignments()->where('user_id', $this->resolveUserId($fromUser));

        if ($type !== null) {
            $query->where('assignment_type', $type);
        }

        return $query->update(['user_id' => $this->resolveUserId($toUser)]);
    }

    public function syncAssignments(array $userIds, string $type = 'primary', ?Model $assignedBy = null): void
    {
        $assignedById = $assignedBy?->id ?? auth()->id();

        $currentAssignments = $this->assignments()
            ->where('assignment_type', $type)
            ->pluck('user_id')
            ->toArray();

        foreach (array_diff($userIds, $currentAssignments) as $userId) {
            /** @noinspection LaravelEloquentGuardedAttributeAssignmentInspection */
            $this->assignments()->create([
                'user_id' => $userId,
                'assignment_type' => $type,
                'assigned_by' => $assignedById,
            ]);
        }

        $toRemove = array_diff($currentAssignments, $userIds);
        if (! empty($toRemove)) {
            $this->assignments()
                ->where('assignment_type', $type)
                ->whereIn('user_id', $toRemove)
                ->delete();
        }
    }

    public function clearAssignments(?string $type = null): int
    {
        $query = $this->assignments();

        if ($type !== null) {
            $query->where('assignment_type', $type);
        }

        return $query->delete();
    }

    public function getAssignmentTypesForUser(int|Model $user): array
    {
        return $this->assignments()
            ->where('user_id', $this->resolveUserId($user))
            ->pluck('assignment_type')
            ->toArray();
    }

    public function hasAssignmentType(int|Model $user, string $type): bool
    {
        return $this->isAssignedTo($user, $type);
    }

    /**
     * @param  array{view?: bool, edit?: bool, transition?: bool}  $overrides
     */
    public function assignWithOverrides(
        int|Model $user,
        array $overrides,
        string $type = 'primary',
        ?Model $assignedBy = null,
    ): WorkflowAssignment {
        $userId = $this->resolveUserId($user);

        $assignment = $this->assignments()
            ->where('user_id', $userId)
            ->where('assignment_type', $type)
            ->first();

        $overrideData = [
            'override_view' => $overrides['view'] ?? null,
            'override_edit' => $overrides['edit'] ?? null,
            'override_transition' => $overrides['transition'] ?? null,
        ];

        if ($assignment) {
            $assignment->update($overrideData);

            return $assignment;
        }

        /** @noinspection LaravelEloquentGuardedAttributeAssignmentInspection */
        $assignment = $this->assignments()->create([
            'user_id' => $userId,
            'assignment_type' => $type,
            'assigned_by' => $assignedBy?->id ?? auth()->id(),
            ...$overrideData,
        ]);

        $assigneeModel = $user instanceof Model ? $user : $assignment->user;
        WorkflowAssigned::dispatch($this, $assigneeModel, $assignedBy, $type);

        return $assignment;
    }

    /**
     * @param  array{view?: bool|null, edit?: bool|null, transition?: bool|null}  $overrides
     */
    public function updateAccessOverrides(int|Model $user, array $overrides, ?string $type = null): bool
    {
        $query = $this->assignments()->where('user_id', $this->resolveUserId($user));

        if ($type !== null) {
            $query->where('assignment_type', $type);
        }

        return $query->update([
            'override_view' => array_key_exists('view', $overrides) ? $overrides['view'] : null,
            'override_edit' => array_key_exists('edit', $overrides) ? $overrides['edit'] : null,
            'override_transition' => array_key_exists('transition', $overrides) ? $overrides['transition'] : null,
        ]) > 0;
    }

    public function hasAccessOverride(int|Model $user, string $accessType): bool
    {
        return $this->assignments()
            ->where('user_id', $this->resolveUserId($user))
            ->where('override_'.$accessType, true)
            ->exists();
    }

    private function resolveUserId(int|Model $user): int
    {
        return $user instanceof Model ? $user->id : $user;
    }
}
