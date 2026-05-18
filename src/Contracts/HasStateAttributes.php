<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Closure;

interface HasStateAttributes
{
    public function getAttribute(): string;

    public function attribute(string|Closure|null $attribute): static;
}
