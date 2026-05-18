<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace RoBYCoNTe\FilamentFlow\Concerns;

use RoBYCoNTe\FilamentFlow\Casts\FlexibleStateCast;
use Spatie\ModelStates\State;

/**
 * Trait to support both PHP State classes and database-only states (strings)
 *
 * @deprecated Use FlexibleStateCast instead. This trait overrides Eloquent internals
 *             and is fragile. Replace with: 'state' => FlexibleStateCast::class.':'.YourState::class
 * @see FlexibleStateCast
 */
trait HasFlexibleStates
{
    /**
     * Fields that should support flexible states (both PHP classes and database strings)
     * Override this in your model if needed
     */
    protected array $flexibleStateFields = ['state'];

    /**
     * Override getAttributeValue to intercept before Spatie's casting
     */
    public function getAttributeValue($key)
    {
        // Check if this is a flexible state field
        if (! in_array($key, $this->flexibleStateFields)) {
            return parent::getAttributeValue($key);
        }

        $value = $this->getAttributeFromArray($key);

        // If no value in attributes, use parent logic
        if ($value === null) {
            return parent::getAttributeValue($key);
        }

        // If it's a string
        if (is_string($value)) {
            // Check if it's a class name
            if (class_exists($value)) {
                // It's a State class name, use parent (Spatie will cast it)
                return parent::getAttributeValue($key);
            }

            // It's a simple string (database-only state)
            // Return as-is, skip Spatie casting
            return $value;
        }

        // For other types, use parent logic
        return parent::getAttributeValue($key);
    }

    /**
     * Override getAttribute to handle database-only states
     */
    public function getAttribute($key)
    {
        // Check if this is a flexible state field
        if (! in_array($key, $this->flexibleStateFields)) {
            return parent::getAttribute($key);
        }

        $value = $this->getAttributeValue($key);

        // If it's already a string (database-only state), return it
        if (is_string($value)) {
            return $value;
        }

        // Otherwise use parent logic (will handle relations, etc.)
        return parent::getAttribute($key);
    }

    /**
     * Override setAttribute to handle database-only states
     */
    public function setAttribute($key, $value)
    {
        // Check if this is a flexible state field
        if (! in_array($key, $this->flexibleStateFields)) {
            return parent::setAttribute($key, $value);
        }

        // If value is a State instance, use parent (Spatie will handle it)
        if ($value instanceof State) {
            return parent::setAttribute($key, $value);
        }

        // If value is a string
        if (is_string($value)) {
            // Check if it's a class name
            if (class_exists($value)) {
                // It's a State class name, use parent (Spatie will handle it)
                return parent::setAttribute($key, $value);
            }

            // It's a simple string (database-only state)
            // Set raw value directly, skip Spatie casting
            $this->attributes[$key] = $value;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Get the State class for a given field
     */
    protected function getStateClassForField(string $field): ?string
    {
        $casts = $this->getCasts();
        $cast = $casts[$field] ?? null;

        if (! $cast) {
            return null;
        }

        // Handle FlexibleStateCast format
        if (str_contains($cast, 'FlexibleStateCast:')) {
            $parts = explode(':', $cast);

            return $parts[1] ?? null;
        }

        return $cast;
    }

    /**
     * Override getCastType to return null for database-only states during serialization
     * This prevents Spatie from trying to cast database-only states
     */
    protected function getCastType($key): ?string
    {
        $castType = parent::getCastType($key);

        // Check if this is a flexible state field
        if (in_array($key, $this->flexibleStateFields)) {
            $value = $this->attributes[$key] ?? null;

            if ($value && is_string($value) && $this->isDatabaseOnlyState($key, $value)) {
                return null;
            }
        }

        return $castType;
    }

    /**
     * Override castAttribute to handle database-only states during serialization
     */
    protected function castAttribute($key, $value)
    {
        // Check if this is a flexible state field
        if (in_array($key, $this->flexibleStateFields) && is_string($value)) {
            if ($this->isDatabaseOnlyState($key, $value)) {
                return $value;
            }
        }

        return parent::castAttribute($key, $value);
    }

    /**
     * Check if a state value is a database-only state (no corresponding PHP class).
     *
     * Spatie's resolveStateClass() returns the input string (not null) when it
     * can't find a matching state class, so we check if the resolved value is
     * a valid class name to determine if it's a real state class.
     */
    protected function isDatabaseOnlyState(string $key, string $value): bool
    {
        // If the value is already a valid class name, it's not database-only
        if (class_exists($value)) {
            return false;
        }

        $stateClass = $this->getStateClassForField($key);
        if (! $stateClass || ! method_exists($stateClass, 'resolveStateClass')) {
            return false;
        }

        $resolved = $stateClass::resolveStateClass($value);

        // resolveStateClass returns: null (null input), a class name (matched), or the input string (no match)
        // It's database-only if resolved is null OR resolved equals the input (no class found)
        return $resolved === null || ! class_exists($resolved);
    }
}
