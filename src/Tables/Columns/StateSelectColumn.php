<?php

namespace RoBYCoNTe\FilamentFlow\Tables\Columns;

use Closure;
use Filament\Tables\Columns\SelectColumn;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateOptions;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateSorting;

class StateSelectColumn extends SelectColumn
{
    use HasStateOptions, HasStateSorting {
        HasStateOptions::extractStateClass insteadof HasStateSorting;
        HasStateSorting::extractStateClass as extractStateClassFromSorting;
    }

    protected Closure|string|null $attribute = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->selectablePlaceholder(false);
        $this->setupOptions();
        $this->setupStateSorting();
    }

    public function getAttribute(?Model $model = null): string
    {
        if ($model === null) {
            $model = $this->getRecord();
        }

        // If attribute is explicitly set, evaluate it with the model
        if ($this->attribute !== null) {
            return $this->evaluate($this->attribute, ['model' => $model]);
        }

        // Otherwise, use the first default state attribute
        if (method_exists($model, 'getDefaultStates')) {
            $defaultStates = $model::getDefaultStates();
            if ($defaultStates && ! $defaultStates->isEmpty()) {
                return (string) array_key_first($defaultStates->toArray());
            }
        }

        return $this->getName();
    }

    public function attribute(string|Closure|null $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }
}
