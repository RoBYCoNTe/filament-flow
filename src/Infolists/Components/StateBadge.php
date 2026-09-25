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

    /**
     * Whether the description the workflow gave the state is read under the badge: what the
     * state means, in the words of the call.
     */
    protected bool $showDescription = false;

    /**
     * A line the host adds under the badge — a deadline, the time spent in the state — when it
     * has one to say.
     */
    protected ?Closure $extraCallback = null;

    public static function make(?string $name = 'state-badge'): static
    {
        // A name humanised by the framework ("Flow state badge") says nothing: the label
        // starts translated, and the host may still name it as it likes.
        return parent::make($name)
            ->label(__('filament-flow::messages.state_badge_label'));
    }

    public function attribute(string|Closure $attribute): static
    {
        $this->stateAttribute = $attribute;

        return $this;
    }

    public function description(bool $show = true): static
    {
        $this->showDescription = $show;

        return $this;
    }

    /** @param Closure(Model, array<string,mixed>|null): ?string $callback */
    public function extra(Closure $callback): static
    {
        $this->extraCallback = $callback;

        return $this;
    }

    public function getStateAttribute(): string
    {
        return $this->evaluate($this->stateAttribute);
    }

    public function getStateMetadata(): ?array
    {
        $record = $this->record();

        if ($record === null) {
            return null;
        }

        $attribute = $this->getStateAttribute();
        $stateValue = $record->{$attribute};

        if (! is_string($stateValue) || $stateValue === '') {
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
        $record = $this->record();
        $stateValue = $record?->{$attribute};

        $metadata = $this->getStateMetadata();

        return $metadata['label'] ?? (is_string($stateValue) ? $stateValue : null);
    }

    public function getStateColor(): ?string
    {
        return $this->getStateMetadata()['color'] ?? null;
    }

    public function getStateIcon(): ?string
    {
        return $this->getStateMetadata()['icon'] ?? null;
    }

    /** The description of the state, when the call declares one and the host asks for it. */
    public function getStateDescription(): ?string
    {
        if (! $this->showDescription) {
            return null;
        }

        $description = $this->getStateMetadata()['description'] ?? null;

        return is_string($description) && trim($description) !== '' ? $description : null;
    }

    /** Whether the state is one the record does not leave. */
    public function getStateIsFinal(): bool
    {
        return (bool) ($this->getStateMetadata()['is_final'] ?? false);
    }

    /**
     * The mark the badge wears: the icon the workflow gave the state, then the one its ending
     * deserves — an approved state and a refused one do not read the same — then the start.
     * Nothing: a plain state is a dot.
     */
    public function getStateMarkerIcon(): ?string
    {
        $metadata = $this->getStateMetadata();

        $icon = $metadata['icon'] ?? null;

        if (is_string($icon) && $icon !== '') {
            return $icon;
        }

        if ($metadata === null) {
            return null;
        }

        if ($metadata['is_final'] ?? false) {
            return ($metadata['color'] ?? null) === 'danger'
                ? 'heroicon-m-x-circle'
                : 'heroicon-m-check-badge';
        }

        if ($metadata['is_initial'] ?? false) {
            return 'heroicon-m-play-circle';
        }

        return null;
    }

    /**
     * Whether the badge draws a dot instead of an icon: only when no mark means something.
     */
    public function showsStateDot(): bool
    {
        return $this->getStateMarkerIcon() === null && $this->getStateLabel() !== null;
    }

    /** The line the host asked for, or null when it has nothing to say. */
    public function getExtraLine(): ?string
    {
        if ($this->extraCallback === null) {
            return null;
        }

        $line = $this->evaluate($this->extraCallback, [
            'record' => $this->record(),
            'metadata' => $this->getStateMetadata(),
        ]);

        return is_string($line) && trim($line) !== '' ? $line : null;
    }

    /**
     * The record the badge draws, when there is one: an entry asked outside a schema has no
     * container to take it from, and answers nothing instead of failing.
     */
    protected function record(): ?Model
    {
        try {
            $record = $this->getRecord();
        } catch (\Throwable) {
            return null;
        }

        return $record instanceof Model ? $record : null;
    }
}
