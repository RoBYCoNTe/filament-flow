<?php

namespace RoBYCoNTe\FilamentFlow\Tables\Columns;

use Closure;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateSorting;
use RoBYCoNTe\FilamentFlow\Services\StateService;

/**
 * A text column for displaying states with custom sort order support.
 *
 * This column displays the state label and supports sorting based on
 * the sort order defined in the state class via HasStateSortOrder interface.
 *
 * @example
 * StateColumn::make('status')
 *     ->badge()
 *     ->sortable()
 */
class StateColumn extends TextColumn
{
    use HasStateSorting;

    protected Closure|string|null $attribute = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupStateDisplay();
        $this->setupStateSorting();
    }

    protected function setupStateDisplay(): void
    {
        $this->state(function ($record) {
            $stateInstance = $record->{$this->getAttribute()};

            if (! $stateInstance) {
                return null;
            }

            // If it's a database-only state (string), get label from database
            if (is_string($stateInstance)) {
                $stateService = app(StateService::class);
                $metadata = $stateService->getStateMetadata(
                    get_class($record),
                    $stateInstance,
                    $this->getAttribute()
                );

                return $metadata['label'] ?? $stateInstance;
            }

            // Get label if available (PHP State class)
            if (method_exists($stateInstance, 'getLabel')) {
                return $stateInstance->getLabel();
            }

            return $stateInstance::getMorphClass();
        });

        // Setup badge colors if states have color metadata
        $this->badge()
            ->color(function ($record) {
                $stateInstance = $record->{$this->getAttribute()};

                if (! $stateInstance) {
                    return null;
                }

                // If it's a database-only state (string), get color from database
                if (is_string($stateInstance)) {
                    $stateService = app(StateService::class);
                    $metadata = $stateService->getStateMetadata(
                        get_class($record),
                        $stateInstance,
                        $this->getAttribute()
                    );

                    return $metadata['color'] ?? null;
                }

                if (method_exists($stateInstance, 'getColor')) {
                    return $stateInstance->getColor();
                }

                return null;
            });

        // Setup icon if states have icon metadata
        $this->icon(function ($record) {
            $stateInstance = $record->{$this->getAttribute()};

            if (! $stateInstance) {
                return null;
            }

            // If it's a database-only state (string), get icon from database
            if (is_string($stateInstance)) {
                $stateService = app(StateService::class);
                $metadata = $stateService->getStateMetadata(
                    get_class($record),
                    $stateInstance,
                    $this->getAttribute()
                );

                return $metadata['icon'] ?? null;
            }

            if (method_exists($stateInstance, 'getIcon')) {
                return $stateInstance->getIcon();
            }

            return null;
        });
    }

    public function getAttribute(?Model $model = null): string
    {
        if ($model === null) {
            $model = $this->getRecord();
        }

        if ($this->attribute !== null) {
            return $this->evaluate($this->attribute);
        }

        // Fallback: try getDefaultStates() if available, otherwise use column name or 'state'
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
