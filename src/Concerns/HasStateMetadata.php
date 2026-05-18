<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use BackedEnum;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\State;

trait HasStateMetadata
{
    /**
     * @return array<string, string|null>
     */
    public static function getStatesLabel(Model|array|string $model): array
    {
        return self::getStateMapping()->mapWithKeys(
            /** @param class-string<State> $stateClass */
            function (string $stateClass) use ($model) {
                return [$stateClass::getMorphClass() => (new $stateClass($model))->getLabel() ?? $stateClass::getMorphClass()];
            }
        )->toArray();
    }

    /**
     * @return array<string, string|array|null>
     */
    public static function getStatesColor(Model|array|string $model): array
    {
        return self::getStateMapping()->mapWithKeys(
            /** @param class-string<State> $stateClass */
            function (string $stateClass) use ($model) {
                $instance = new $stateClass($model);
                if (method_exists($instance, 'getColor')) {
                    return [$stateClass::getMorphClass() => $instance->getColor()];
                }

                return [$stateClass::getMorphClass() => null];
            }
        )->toArray();
    }

    /**
     * @return array<string, string|Htmlable|null>
     */
    public static function getStatesDescription(Model|array|string $model): array
    {
        return self::getStateMapping()->mapWithKeys(
            /** @param class-string<State> $stateClass */
            function (string $stateClass) use ($model) {
                $instance = new $stateClass($model);
                // Check if the class implements HasDescription interface before calling getDescription
                if (method_exists($instance, 'getDescription')) {
                    return [$stateClass::getMorphClass() => $instance->getDescription()];
                }

                return [$stateClass::getMorphClass() => null];
            }
        )->toArray();
    }

    /**
     * @return array<string, string|BackedEnum|null>
     */
    public static function getStatesIcon(Model|array|string $model): array
    {
        return self::getStateMapping()->mapWithKeys(
            /** @param class-string<State> $stateClass */
            function (string $stateClass) use ($model) {
                $instance = new $stateClass($model);
                if (method_exists($instance, 'getIcon')) {
                    return [$stateClass::getMorphClass() => $instance->getIcon()];
                }

                return [$stateClass::getMorphClass() => null];
            }
        )->toArray();
    }
}
