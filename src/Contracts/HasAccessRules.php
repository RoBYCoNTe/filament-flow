<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

/**
 * Interface for State classes that define their own access rules (Code-First approach)
 *
 * Implement this interface on your State classes to define who can view, edit,
 * create, or transition records when they are in that state.
 *
 * Note: getCreateAccessRules() only applies to the INITIAL state. It defines
 * who can create new records (which will start in this state).
 *
 * Available rule tokens:
 * - '*'                    : Everyone (including guests)
 * - '@authenticated'       : Any authenticated user
 * - '@owner'               : Record owner (uses owner_field config)
 * - '@assigned'            : Any user assigned to the record
 * - '@assigned:type'       : User assigned with specific type (e.g., @assigned:primary)
 * - 'role:name'            : User with specific role
 * - 'role:name1,name2'     : User with any of the specified roles
 * - 'permission:name'      : User with specific permission
 *
 * Example:
 * ```php
 * final class PendingState extends OrderState implements HasAccessRules
 * {
 *     public static function getCreateAccessRules(): array
 *     {
 *         return ['role:sales,admin']; // Only sales and admin can create orders
 *     }
 *
 *     public static function getViewAccessRules(): array
 *     {
 *         return ['@authenticated'];
 *     }
 *
 *     public static function getEditAccessRules(): array
 *     {
 *         return ['@owner', '@assigned:primary'];
 *     }
 *
 *     public static function getTransitionAccessRules(): array
 *     {
 *         return ['role:manager,admin'];
 *     }
 * }
 * ```
 */
interface HasAccessRules
{
    /**
     * Get access rules for creating records in this state (only applies to initial state)
     *
     * This defines who can create new records. Since new records start in the
     * initial state, these rules are checked when creating a new record.
     *
     * @return array<string> Array of rule tokens (combined with OR logic)
     */
    public static function getCreateAccessRules(): array;

    /**
     * Get access rules for viewing records in this state
     *
     * @return array<string> Array of rule tokens (combined with OR logic)
     */
    public static function getViewAccessRules(): array;

    /**
     * Get access rules for editing records in this state
     *
     * @return array<string> Array of rule tokens (combined with OR logic)
     */
    public static function getEditAccessRules(): array;

    /**
     * Get access rules for transitioning records from this state
     *
     * @return array<string> Array of rule tokens (combined with OR logic)
     */
    public static function getTransitionAccessRules(): array;
}
