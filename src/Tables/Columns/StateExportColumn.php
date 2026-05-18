<?php

namespace RoBYCoNTe\FilamentFlow\Tables\Columns;

use Closure;
use Filament\Actions\Exports\ExportColumn;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Services\StateService;
use Spatie\ModelStates\State;

/**
 * StateExportColumn component for exporting model states to Excel or CSV.
 *
 * This component extends Filament's ExportColumn functionality to automatically
 * export the state value with proper label formatting. It generates labels
 * automatically from the HasLabel interface if implemented by the state,
 * or falls back to the state's morph class name.
 *
 * @example
 * ```php
 * use RoBYCoNTe\FilamentFlow\Tables\Columns\StateExportColumn;
 *
 * StateExportColumn::make('state')
 *     ->label('Order Status')
 * ```
 */
class StateExportColumn extends ExportColumn
{
    protected string|Closure|null $stateAttribute = null;

    /**
     * Get the default name for the export column.
     */
    public static function getDefaultName(): ?string
    {
        return 'state';
    }

    /**
     * Set the state attribute name.
     *
     * @noinspection PhpUnused
     */
    public function stateAttribute(string|Closure|null $attribute): static
    {
        $this->stateAttribute = $attribute;

        return $this;
    }

    /**
     * Get the state attribute name.
     */
    public function getStateAttribute(): string
    {
        if ($this->stateAttribute !== null) {
            return $this->evaluate($this->stateAttribute);
        }

        // Get the first state attribute from the model's default states
        $record = $this->getRecord();
        if ($record && method_exists($record, 'getDefaultStates')) {
            $defaultStates = $record::getDefaultStates();
            if ($defaultStates && ! $defaultStates->isEmpty()) {
                return (string) array_key_first($defaultStates->toArray());
            }
        }

        return 'state';
    }

    /**
     * Get the state value formatted for export.
     */
    protected function getStateLabel(Model $record): ?string
    {
        $stateValue = $record->getAttribute($this->getStateAttribute());

        if ($stateValue instanceof State) {
            // If the state implements HasLabel, use getLabel()
            if ($stateValue instanceof HasLabel) {
                $label = $stateValue->getLabel();
                if ($label !== null) {
                    return (string) $label;
                }
            }

            // Fall back to morph class
            return $stateValue::getMorphClass();
        }

        // If it's a database-only state (string), get label from database
        if (is_string($stateValue)) {
            $stateService = app(StateService::class);
            $metadata = $stateService->getStateMetadata(
                get_class($record),
                $stateValue,
                $this->getStateAttribute()
            );

            if ($metadata && isset($metadata['label'])) {
                return $metadata['label'];
            }
        }

        return (string) $stateValue;
    }

    /**
     * Configure the column to format state values properly.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Format the state value using the label
        $this->state(function (Model $record): string {
            return $this->getStateLabel($record);
        });
    }
}
