<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\State;

/**
 * Trait to implement state sort order functionality.
 * Provides methods to get sort order values for states.
 */
trait HasStateSortOrder
{
    /**
     * Get a mapping of state morph classes to their sort order.
     *
     * @return array<string, int>
     */
    public static function getStatesSortOrder(Model|array|string $model): array
    {
        return self::getStateMapping()->mapWithKeys(
            /** @param class-string<State> $stateClass */
            function (string $stateClass) {
                if (method_exists($stateClass, 'getSortOrder')) {
                    return [$stateClass::getMorphClass() => $stateClass::getSortOrder()];
                }

                return [$stateClass::getMorphClass() => 999];
            }
        )->toArray();
    }

    /**
     * Get the sort order value for the current state instance.
     *
     * @noinspection PhpUndefinedMethodInspection
     */
    public function getSortOrderValue(): int
    {
        if (method_exists($this, 'getSortOrder')) {
            return static::getSortOrder();
        }

        return 999;
    }
}
