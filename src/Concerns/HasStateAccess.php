<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Services\WorkflowFieldPermissionsService;
use RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService;

/**
 * Trait to add state-based access control to Eloquent models
 *
 * This trait provides methods to check if a user can view, edit, create, or
 * transition a record based on its current state and configured access rules.
 *
 * Usage:
 * 1. Add this trait to your model
 * 2. Configure access rules in your State classes or database
 * 3. Use the provided methods to check access
 *
 * Example:
 * ```php
 * class Order extends Model
 * {
 *     use HasStateAccess;
 * }
 *
 * // Check create access (static method - no record yet)
 * if (Order::canBeCreatedBy($user)) { ... }
 *
 * // Check access on existing records
 * if ($order->canBeViewedBy($user)) { ... }
 * if ($order->canBeEditedBy($user)) { ... }
 *
 * // Filter query
 * $orders = Order::visibleTo($user)->get();
 * $orders = Order::editableBy($user)->get();
 * ```
 */
trait HasStateAccess
{
    /**
     * Get the access service instance
     */
    protected static function getAccessService(): WorkflowStateAccessService
    {
        return app(WorkflowStateAccessService::class);
    }

    /**
     * Check if a user can create a new record of this model
     *
     * This is a STATIC method because the record doesn't exist yet.
     * It checks the create access rules on the INITIAL state.
     *
     * @param  Model|null  $user  The user to check (defaults to authenticated user)
     */
    public static function canBeCreatedBy(?Model $user = null): bool
    {
        return static::getAccessService()->canCreate(static::class, $user);
    }

    /**
     * Check if a user can view this record
     *
     * @param  Model|null  $user  The user to check (defaults to authenticated user)
     */
    public function canBeViewedBy(?Model $user = null): bool
    {
        return static::getAccessService()->canView($this, $user);
    }

    /**
     * Check if a user can edit this record
     *
     * @param  Model|null  $user  The user to check (defaults to authenticated user)
     */
    public function canBeEditedBy(?Model $user = null): bool
    {
        return static::getAccessService()->canEdit($this, $user);
    }

    /**
     * Check if a user can transition this record to another state
     *
     * @param  Model|null  $user  The user to check (defaults to authenticated user)
     * @param  string|null  $toState  Optional: the target state to check
     */
    public function canBeTransitionedBy(?Model $user = null, ?string $toState = null): bool
    {
        return static::getAccessService()->canTransition($this, $user, $toState);
    }

    /**
     * Scope: Only records visible to the given user
     *
     * @param  Model|null  $user  The user to check (defaults to authenticated user)
     */
    public function scopeVisibleTo(Builder $query, ?Model $user = null): Builder
    {
        return static::getAccessService()->scopeAccessible($query, $user);
    }

    /**
     * Scope: Only records editable by the given user
     *
     * @param  Model|null  $user  The user to check (defaults to authenticated user)
     */
    public function scopeEditableBy(Builder $query, ?Model $user = null): Builder
    {
        return static::getAccessService()->scopeAccessible($query, $user, 'edit');
    }

    /**
     * Scope: Only records that the user can transition
     *
     * @param  Model|null  $user  The user to check (defaults to authenticated user)
     */
    public function scopeTransitionableBy(Builder $query, ?Model $user = null): Builder
    {
        return static::getAccessService()->scopeAccessible($query, $user, 'transition');
    }

    /**
     * Get the access rules for the current state
     *
     * @param  string  $accessType  (create, view, edit, transition)
     * @return array<string>
     */
    public function getStateAccessRules(string $accessType = 'view'): array
    {
        $state = $this->state ?? null;

        if ($state === null) {
            return config('filament-flow.state_access.defaults.'.$accessType, ['@authenticated']);
        }

        return static::getAccessService()->getAccessRules($state, $accessType);
    }

    /**
     * Check if state access control is enabled
     */
    public static function isStateAccessEnabled(): bool
    {
        return static::getAccessService()->isEnabled();
    }

    /**
     * Check if a field (form field or RelationManager action) is visible for the current state.
     *
     * Returns true if the field is not configured (default: visible).
     *
     * @param  Model|null  $user  The user to check (defaults to authenticated user)
     */
    public function isFieldVisible(string $fieldName, ?Model $user = null): bool
    {
        $user ??= auth()->user();

        $perms = app(WorkflowFieldPermissionsService::class)
            ->getFieldPermissions($this, $user);

        $perm = $perms[$fieldName] ?? null;

        if (! $perm) {
            return true;
        }

        return ($perm['visible'] ?? true) && ! ($perm['locked'] ?? false);
    }

    /**
     * Check if a field is readonly for the current state.
     *
     * @param  Model|null  $user  The user to check (defaults to authenticated user)
     */
    public function isFieldReadonly(string $fieldName, ?Model $user = null): bool
    {
        $user ??= auth()->user();

        $perms = app(WorkflowFieldPermissionsService::class)
            ->getFieldPermissions($this, $user);

        return $perms[$fieldName]['readonly'] ?? false;
    }
}
