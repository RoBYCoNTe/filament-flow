<?php

namespace RoBYCoNTe\FilamentFlow\Tables\Grouping;

use Filament\Tables\Grouping\Group;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Services\StateService;
use Spatie\ModelStates\State;

/**
 * StateGroup component for grouping table records by their state.
 *
 * This component extends Filament's Group functionality to automatically
 * group records based on their state attribute. It generates labels
 * automatically from the state's morph class, or from the HasLabel interface
 * if implemented by the state.
 *
 * @example
 * ```php
 * use RoBYCoNTe\FilamentFlow\Tables\Grouping\StateGroup;
 *
 * StateGroup::make('state')
 *     ->label('Order Status')
 *     ->collapsible()
 * ```
 */
class StateGroup extends Group
{
    protected string $stateAttribute = 'state';

    /**
     * Create a new StateGroup instance.
     *
     * @param  string|null  $id  The group identifier (default: 'state')
     */
    public static function make(?string $id = 'state'): static
    {
        $static = parent::make($id);
        $static->stateAttribute = $id ?? 'state';

        return $static;
    }

    /**
     * Get the attribute name for the state field.
     */
    public function getStateAttribute(): string
    {
        return $this->stateAttribute;
    }

    /**
     * Set the attribute name for the state field.
     *
     * @noinspection PhpUnused
     */
    public function stateAttribute(string $attribute): static
    {
        $this->stateAttribute = $attribute;

        return $this;
    }

    /**
     * Get the label for a specific state group.
     *
     * This method generates labels automatically:
     * - If the state implements HasLabel, uses getLabel()
     * - For database-only states (strings), retrieves label from database
     * - Otherwise, uses the state's morph class name
     */
    protected function getStateLabel(Model $record): string
    {
        $stateValue = $record->getAttribute($this->getStateAttribute());

        if ($stateValue instanceof State) {
            if (method_exists($stateValue, 'getLabel')) {
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
     * Configure the group to use state-based labeling.
     */
    public function setUp(): void
    {
        parent::setUp();

        // Set up title formatting using state labels
        $this->getTitleFromRecordUsing(fn (Model $record): string => $this->getStateLabel($record));

        // Use getKeyFromRecordUsing to avoid adding an extra column
        // This gets the key without requiring a visible column
        $this->getKeyFromRecordUsing(function (Model $record) {
            $stateValue = $record->getAttribute($this->getStateAttribute());

            if ($stateValue instanceof State) {
                return $stateValue::getMorphClass();
            }

            return $stateValue;
        });
    }
}
