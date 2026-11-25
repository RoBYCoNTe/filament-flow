<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use InvalidArgumentException;
use RoBYCoNTe\FilamentFlow\Contracts\TransferTargetResolver;

/**
 * The way a record can be handed to a person that is not listed yet: the resolver the host named
 * in the configuration, or none. With none the panel offers only the people it already knows, as
 * it always did.
 */
final class TransferTargets
{
    public static function resolver(): ?TransferTargetResolver
    {
        $class = config('filament-flow.state_access.transfer_target_resolver');

        if (blank($class)) {
            return null;
        }

        if (! is_string($class) || ! is_a($class, TransferTargetResolver::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'The transfer target resolver [%s] must be a class that implements %s.',
                is_string($class) ? $class : get_debug_type($class),
                TransferTargetResolver::class,
            ));
        }

        return app($class);
    }
}
