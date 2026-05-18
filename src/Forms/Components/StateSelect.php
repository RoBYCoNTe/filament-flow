<?php

namespace RoBYCoNTe\FilamentFlow\Forms\Components;

use Filament\Forms\Components\Select;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateAttributes;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateOptions;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateAttributes as HasStateAttributesContract;

/**
 * @method static static make(string $name)
 */
class StateSelect extends Select implements HasStateAttributesContract
{
    use HasStateAttributes;
    use HasStateOptions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupOptions();
    }
}
