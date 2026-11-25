<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use InvalidArgumentException;
use RoBYCoNTe\FilamentFlow\Contracts\AccessControlGate;

/**
 * Which gate answers for a panel: the one the call names, the one of `superAdminOnly`, the one
 * of the configuration, and — when nobody said anything — the historical one, in that order.
 *
 * A call that asks for the super administrators only stays as strict as it asked, whatever the
 * configuration opens to others.
 */
final class AccessGate
{
    /**
     * @param  class-string<AccessControlGate>|string|null  $gate  The class the call names
     */
    public static function resolve(?string $gate = null, bool $superAdminOnly = false): AccessControlGate
    {
        $class = $gate
            ?: ($superAdminOnly ? SuperAdminAccessGate::class : null)
            ?: config('filament-flow.state_access.access_control_gate')
            ?: AdminAccessGate::class;

        if (! is_string($class) || ! is_a($class, AccessControlGate::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'The access control gate [%s] must be a class that implements %s.',
                is_string($class) ? $class : get_debug_type($class),
                AccessControlGate::class,
            ));
        }

        return app($class);
    }
}
