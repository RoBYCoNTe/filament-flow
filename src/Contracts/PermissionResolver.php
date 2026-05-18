<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface for resolving user permissions
 *
 * Implement this interface to customize how user permissions are checked.
 * This allows integration with various permission management systems like
 * Spatie Permission, Laravel Gates, Bouncer, or custom implementations.
 */
interface PermissionResolver
{
    /**
     * Check if user has a specific permission
     *
     * @param  Model  $user  The user model
     * @param  string  $permission  Permission name to check
     * @param  Model|null  $record  Optional record for contextual permissions
     */
    public function hasPermission(Model $user, string $permission, ?Model $record = null): bool;

    /**
     * Check if user has any of the specified permissions
     *
     * @param  Model  $user  The user model
     * @param  array<string>  $permissions  Permission names to check
     * @param  Model|null  $record  Optional record for contextual permissions
     */
    public function hasAnyPermission(Model $user, array $permissions, ?Model $record = null): bool;

    /**
     * Check if user has all the specified permissions
     *
     * @param  Model  $user  The user model
     * @param  array<string>  $permissions  Permission names to check
     * @param  Model|null  $record  Optional record for contextual permissions
     */
    public function hasAllPermissions(Model $user, array $permissions, ?Model $record = null): bool;
}
