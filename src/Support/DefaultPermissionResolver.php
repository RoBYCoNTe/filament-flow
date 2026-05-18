<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use RoBYCoNTe\FilamentFlow\Contracts\PermissionResolver;

/**
 * Default permission resolver implementation
 *
 * This implementation supports:
 * - Spatie Permission package (if installed)
 * - Laravel Gates
 * - Models with can() method
 */
class DefaultPermissionResolver implements PermissionResolver
{
    /**
     * {@inheritDoc}
     */
    public function hasPermission(Model $user, string $permission, ?Model $record = null): bool
    {
        // Check for Spatie Permission
        if (method_exists($user, 'hasPermissionTo')) {
            return $user->hasPermissionTo($permission);
        }

        // Check Laravel Gate
        if ($record) {
            return Gate::forUser($user)->allows($permission, $record);
        }

        return Gate::forUser($user)->allows($permission);
    }

    /**
     * {@inheritDoc}
     */
    public function hasAnyPermission(Model $user, array $permissions, ?Model $record = null): bool
    {
        // Check for Spatie Permission
        if (method_exists($user, 'hasAnyPermission')) {
            return $user->hasAnyPermission($permissions);
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermission($user, $permission, $record)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function hasAllPermissions(Model $user, array $permissions, ?Model $record = null): bool
    {
        // Check for Spatie Permission
        if (method_exists($user, 'hasAllPermissions')) {
            return $user->hasAllPermissions($permissions);
        }

        foreach ($permissions as $permission) {
            if (! $this->hasPermission($user, $permission, $record)) {
                return false;
            }
        }

        return true;
    }
}
