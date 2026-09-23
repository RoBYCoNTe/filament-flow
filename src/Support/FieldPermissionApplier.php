<?php

namespace RoBYCoNTe\FilamentFlow\Support;

/**
 * Applying the permissions of a state to a component: what is locked or invisible becomes
 * hidden, what is read-only becomes disabled, and what is required stays required.
 */
class FieldPermissionApplier
{
    /**
     * Apply field permissions to form components recursively.
     *
     * @param  array  $components  Filament form components
     * @param  array  $permissions  Field permission config keyed by field name
     * @return array Modified components
     */
    public static function apply(array $components, array $permissions): array
    {
        return array_map(function ($component) use ($permissions) {
            $name = ComponentIdentifier::resolve($component);

            if ($name && isset($permissions[$name])) {
                $config = $permissions[$name];

                // Locked = completely hidden
                if ($config['locked'] ?? false) {
                    $component->hidden();

                    return $component;
                }

                // Hidden
                if (! ($config['visible'] ?? true)) {
                    $component->hidden();

                    return $component;
                }

                // Readonly
                if ($config['readonly'] ?? false) {
                    if (method_exists($component, 'disabled')) {
                        $component->disabled();
                    }
                }

                // Required
                if ($config['required'] ?? false) {
                    $component->required();
                }
            }

            // If component has children, apply recursively
            if (method_exists($component, 'getChildComponents')) {
                try {
                    $children = $component->getChildComponents();
                    if (! empty($children)) {
                        $modifiedChildren = static::apply($children, $permissions);
                        if (method_exists($component, 'schema')) {
                            $component->schema($modifiedChildren);
                        }
                    }
                } catch (\Throwable) {
                    // Component may not have a container context (e.g. standalone usage)
                }
            }

            return $component;
        }, $components);
    }
}
