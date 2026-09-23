<?php

namespace RoBYCoNTe\FilamentFlow\Infolists\Components;

use Closure;
use Filament\Infolists\Components\Entry;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Services\StateService;

/**
 * The badge of the state of a record: its label, its colour and its icon, read from the
 * workflow.
 *
 * The tenant of the row is part of the question — a workflow scoped to an owner is found only
 * when the owner is named — and without it the badge comes out empty, in silence.
 */
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

        // The tenant of the row has to be passed: the workflow of a call lives under its
        // scheme, and without it the service does not find the states to take label, colour and
        // icon from — which is how a badge comes out empty.
        return app(StateService::class)->getStateMetadata(
            get_class($record),
            $stateValue,
            $attribute,
            method_exists($record, 'getWorkflowTenantId') ? $record->getWorkflowTenantId() : null,
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
