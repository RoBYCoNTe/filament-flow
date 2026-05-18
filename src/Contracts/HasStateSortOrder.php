<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

/**
 * Define the contract for state sort order.
 * Allows states to define a custom sort order for table columns.
 *
 * @example
 * class PendingState extends State implements HasStateSortOrder
 * {
 *     public static function getSortOrder(): int
 *     {
 *         return 1;
 *     }
 * }
 *
 * class ProcessingState extends State implements HasStateSortOrder
 * {
 *     public static function getSortOrder(): int
 *     {
 *         return 2;
 *     }
 * }
 */
interface HasStateSortOrder
{
    /**
     * Get the sort order for the state.
     * Lower values will be sorted first.
     */
    public static function getSortOrder(): int;
}
