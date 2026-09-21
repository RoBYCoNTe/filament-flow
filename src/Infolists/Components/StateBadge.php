<?php

namespace RoBYCoNTe\FilamentFlow\Infolists\Components;

use Closure;
use Filament\Infolists\Components\Entry;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Services\StateService;

class StateBadge extends Entry
{
    protected string $view = 'filament-flow::infolists.state-badge';

    protected Closure|string $stateAttribute = 'state';

    public static function make(?string $name = 'state-badge'): static
    {
        return parent::make($name);
    }

    public function attribute(string|Closure $attribute): static
    {
        $this->stateAttribute = $attribute;

        return $this;
    }

    public function getStateAttribute(): string
    {
        return $this->evaluate($this->stateAttribute);
    }

    public function getStateMetadata(): ?array
    {
        $record = $this->getRecord();

        if (! $record instanceof Model) {
            return null;
        }

        $attribute = $this->getStateAttribute();
        $stateValue = $record->{$attribute};

        if (! $stateValue || ! is_string($stateValue)) {
            return null;
        }

        return app(StateService::class)->getStateMetadata(
            get_class($record),
            $stateValue,
            $attribute,
        );
    }

    public function getStateLabel(): ?string
    {
        $attribute = $this->getStateAttribute();
        $record = $this->getRecord();
        $stateValue = $record?->{$attribute};

        $metadata = $this->getStateMetadata();

        return $metadata['label'] ?? $stateValue;
    }

    public function getStateColor(): ?string
    {
        return $this->getStateMetadata()['color'] ?? null;
    }

    public function getStateIcon(): ?string
    {
        return $this->getStateMetadata()['icon'] ?? null;
    }
}
