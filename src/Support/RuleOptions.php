<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Shared options for rule/condition selects across Access Rules and Field Permission overrides.
 */
class RuleOptions
{
    /**
     * Full rule options for Access Rules (who can view/edit/transition/create).
     * Roles are prefixed with "role:", permissions with "permission:".
     */
    public static function forAccessRules(): array
    {
        return static::build(
            includeGeneral: true,
            includeRelationship: true,
            includeRoles: true,
            includePermissions: true,
            prefixRoles: true,
        );
    }

    /**
     * Options for Field Permission conditional overrides.
     * Only relationship conditions and plain role names (no prefix).
     */
    public static function forFieldOverrides(): array
    {
        return static::build(
            includeGeneral: false,
            includeRelationship: true,
            includeRoles: true,
            includePermissions: false,
            prefixRoles: false,
        );
    }

    /**
     * Build grouped options based on flags.
     *
     * @return array<string, array<string, string>>
     */
    protected static function build(
        bool $includeGeneral,
        bool $includeRelationship,
        bool $includeRoles,
        bool $includePermissions,
        bool $prefixRoles,
    ): array {
        $options = [];

        if ($includeGeneral) {
            $options[__('General')] = [
                '*' => __('* — Everyone, no restrictions'),
                '@authenticated' => __('@authenticated — Any logged-in user'),
            ];
        }

        if ($includeRelationship) {
            $options[__('Record relationship')] = [
                '@owner' => __('@owner — The user who owns the record'),
                '@assigned' => __('@assigned — Any user assigned to the record'),
                '@assigned:primary' => __('@assigned:primary — Primary assignee'),
                '@assigned:secondary' => __('@assigned:secondary — Secondary assignee'),
                '@assigned:viewer' => __('@assigned:viewer — Viewer assignee'),
            ];
        }

        if ($includeRoles && class_exists(Role::class)) {
            $roles = Role::pluck('name', 'name')->toArray();
            if ($roles) {
                $roleOptions = [];
                foreach ($roles as $name) {
                    $key = $prefixRoles ? "role:{$name}" : $name;
                    $roleOptions[$key] = $prefixRoles ? "role:{$name}" : $name;
                }
                $options[__('Roles')] = $roleOptions;
            }
        }

        if ($includePermissions && class_exists(Permission::class)) {
            $permissions = Permission::pluck('name', 'name')->toArray();
            if ($permissions) {
                $permOptions = [];
                foreach ($permissions as $name) {
                    $permOptions["permission:{$name}"] = "permission:{$name}";
                }
                $options[__('Permissions')] = $permOptions;
            }
        }

        return $options;
    }
}
