<?php

namespace RoBYCoNTe\FilamentFlow\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \RoBYCoNTe\FilamentFlow\FilamentFlow
 */
class FilamentFlow extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \RoBYCoNTe\FilamentFlow\FilamentFlow::class;
    }
}
