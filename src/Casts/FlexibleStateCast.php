<?php

namespace RoBYCoNTe\FilamentFlow\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\State;

/**
 * Custom cast that handles both PHP State classes and database-only states (strings)
 *
 * This cast extends Spatie's functionality by allowing states that don't have PHP classes.
 * When a state value is found in database that doesn't correspond to a PHP class,
 * it returns the string value as-is instead of throwing an error.
 */
class FlexibleStateCast implements CastsAttributes
{
    protected string $stateClass;

    public function __construct(string $stateClass)
    {
        $this->stateClass = $stateClass;
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): State|string|null
    {
        if ($value === null) {
            return null;
        }

        // Try to resolve as a PHP State class first
        /** @noinspection PhpUndefinedMethodInspection */
        $stateClass = $this->stateClass::resolveStateClass($value);

        if ($stateClass && class_exists($stateClass)) {
            $state = new $stateClass($model);
            // Set the field property so Spatie's transition mechanism works
            if (method_exists($state, 'setField')) {
                $state->setField($key);
            }

            return $state;
        }

        // If resolveStateClass returns null, it's a database-only state
        // Return as string
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        // If it's already a string (database-only state), return as-is
        if (is_string($value)) {
            return $value;
        }

        // If it's a State instance, get its morph class
        if ($value instanceof State) {
            return $value::getMorphClass();
        }

        // If it's a State class name, instantiate and get morph class
        if (is_subclass_of($value, State::class)) {
            return $value::getMorphClass();
        }

        return $value;
    }
}
