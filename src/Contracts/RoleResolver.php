<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface for resolving user roles
 *
 * Implement this interface to customize how user roles are retrieved.
 * This allows integration with various role management systems like
 * Spatie Permission, Bouncer, or custom implementations.
 */
interface RoleResolver
{
    /**
     * Get all roles for a user
     *
     * @param  Model  $user  The user model
     * @return array<string> Array of role names
     */
    public function getRoles(Model $user): array;

    /**
     * Check if user has any of the specified roles
     *
     * @param  Model  $user  The user model
     * @param  array<string>  $roles  Role names to check
     */
    public function hasAnyRole(Model $user, array $roles): bool;

    /**
     * Check if user has all the specified roles
     *
     * @param  Model  $user  The user model
     * @param  array<string>  $roles  Role names to check
     */
    public function hasAllRoles(Model $user, array $roles): bool;

    /**
     * Check if user is a super admin (bypasses all access checks)
     *
     * @param  Model  $user  The user model
     */
    public function isSuperAdmin(Model $user): bool;
}
