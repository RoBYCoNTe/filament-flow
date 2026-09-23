<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Closure;

/**
 * The contract of whatever carries a state attribute: which column of the record holds it.
 */
interface HasStateAttributes
{
    public function getAttribute(): string;

    public function attribute(string|Closure|null $attribute): static;
}
