<?php

namespace RoBYCoNTe\FilamentFlow\Forms\Components;

use Filament\Forms\Components\ToggleButtons;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateAttributes;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateOptions;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateAttributes as HasStateAttributesContract;

class StateToggleButtons extends ToggleButtons implements HasStateAttributesContract
{
    use HasStateAttributes;
    use HasStateOptions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupOptions();
        $this->icons(function ($model) {
            $stateClass = $this->extractStateClass((new $model)->getCasts()[$this->getAttribute()] ?? null);
            if ($stateClass && method_exists($stateClass, 'getStatesIcon')) {
                return $stateClass::getStatesIcon($model);
            }

            return [];
        });
        $this->colors(function ($model) {
            $stateClass = $this->extractStateClass((new $model)->getCasts()[$this->getAttribute()] ?? null);
            if (method_exists($stateClass, 'getStatesColor')) {
                return $stateClass::getStatesColor($model);
            }

            return [];
        });
    }
}
