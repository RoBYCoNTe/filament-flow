<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\RoleResolver;

/**
 * Default role resolver implementation
 *
 * This implementation supports:
 * - Spatie Permission package (if installed)
 * - Models with a 'role' or 'roles' attribute
 * - Models with a getRoles() method
 */
class DefaultRoleResolver implements RoleResolver
{
    /**
     * {@inheritDoc}
     */
    public function getRoles(Model $user): array
    {
        // Check for Spatie Permission
        if (method_exists($user, 'getRoleNames')) {
            return $user->getRoleNames()->toArray();
        }

        // Check for custom getRoles method
        if (method_exists($user, 'getRoles')) {
            $roles = $user->getRoles();

            /** @noinspection PhpConditionAlreadyCheckedInspection */
            return is_array($roles) ? $roles : $roles->toArray();
        }

        // Check for roles relationship
        if (method_exists($user, 'roles')) {
            /** @noinspection PhpUndefinedFieldInspection */
            $roles = $user->roles;
            if ($roles) {
                return $roles->pluck('name')->toArray();
            }
        }

        // Check for single role attribute
        if (isset($user->role)) {
            return is_array($user->role) ? $user->role : [$user->role];
        }

        // Check for roles attribute (array)
        if (isset($user->roles) && is_array($user->roles)) {
            return $user->roles;
        }

        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function hasAnyRole(Model $user, array $roles): bool
    {
        // Check for Spatie Permission
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }

        $userRoles = $this->getRoles($user);

        foreach ($roles as $role) {
            if (in_array($role, $userRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function hasAllRoles(Model $user, array $roles): bool
    {
        // Check for Spatie Permission
        if (method_exists($user, 'hasAllRoles')) {
            return $user->hasAllRoles($roles);
        }

        $userRoles = $this->getRoles($user);

        foreach ($roles as $role) {
            if (! in_array($role, $userRoles, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isSuperAdmin(Model $user): bool
    {
        $superAdminRoles = config('filament-flow.state_access.super_admin_roles', ['super_admin']);

        return $this->hasAnyRole($user, $superAdminRoles);
    }
}
