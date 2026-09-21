<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

/**
 * Reads the state class out of a model cast definition, supporting both a plain
 * state class and the FlexibleStateCast format
 * (`RoBYCoNTe\FilamentFlow\Casts\FlexibleStateCast:App\States\Order\OrderState`).
 */
trait ParsesStateCast
{
    protected function extractStateClass(?string $cast): ?string
    {
        if (! $cast) {
            return null;
        }

        if (! str_contains($cast, 'FlexibleStateCast:')) {
            return $cast;
        }

        $parts = explode(':', $cast);

        return $parts[1] ?? null;
    }
}
