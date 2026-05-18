<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;

class ComponentIdentifier
{
    /**
     * Resolve a stable identifier for any form component.
     *
     * - Field → getName()
     * - Layout with explicit string key → the key
     * - Layout with Closure/null key → null (not controllable)
     */
    public static function resolve(Component $component): ?string
    {
        if ($component instanceof Field) {
            return $component->getName();
        }

        try {
            $ref = new \ReflectionProperty($component, 'key');
            $key = $ref->getValue($component);

            return is_string($key) && filled($key) ? $key : null;
        } catch (\ReflectionException) {
            return null;
        }
    }
}
