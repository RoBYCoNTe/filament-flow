<?php

namespace RoBYCoNTe\FilamentFlow\Forms\Components;

use Filament\Forms\Components\Radio;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateAttributes;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateOptions;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateAttributes as HasStateAttributesContract;

class StateRadio extends Radio implements HasStateAttributesContract
{
    use HasStateAttributes;
    use HasStateOptions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupOptions();
        $this->descriptions(function ($model) {
            $stateClass = $this->extractStateClass((new $model)->getCasts()[$this->getAttribute()] ?? null);

            /** @noinspection PhpUndefinedMethodInspection */
            return $stateClass ? $stateClass::getStatesDescription($model) : [];
        });
    }
}
