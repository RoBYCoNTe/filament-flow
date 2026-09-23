<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Closure;

/**
 * The attribute a state action works on: `state` unless the class names another column.
 */
trait HasStateAttributes
{
    protected Closure|string|null $attribute = null;

    public function getAttribute(): string
    {
        if ($this->attribute !== null) {
            return $this->evaluate($this->attribute);
        }

        // Fallback to default state if no attribute specified
        $modelClass = $this->getModel();
        if (! $modelClass || ! method_exists($modelClass, 'getDefaultStates')) {
            return 'state'; // Default fallback
        }

        $defaultStates = $modelClass::getDefaultStates();
        if (! $defaultStates || $defaultStates->isEmpty()) {
            return 'state'; // Default fallback
        }

        return (string) array_key_first($defaultStates->toArray());
    }

    public function attribute(string|Closure|null $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }
}
