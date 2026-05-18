<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for adding custom state-based sorting to table columns.
 *
 * This trait provides a common implementation for sorting columns
 * based on the sort order defined in state classes via HasStateSortOrder interface.
 */
trait HasStateSorting
{
    public function setupStateSorting(): void
    {
        $this->sortable(query: function (Builder $query, string $direction): Builder {
            return $this->applySort($query, $direction);
        });
    }

    public function applySort(Builder $query, string $direction = 'asc'): Builder
    {
        $model = $query->getModel();
        $attribute = $this->getAttribute($model);
        $cast = $model->getCasts()[$attribute] ?? null;
        $stateClass = $this->extractStateClass($cast);

        if (! $stateClass) {
            return $query->orderBy($attribute, $direction);
        }

        // Check if the state class has getSortOrder method
        if (! method_exists($stateClass, 'getStatesSortOrder')) {
            return $query->orderBy($attribute, $direction);
        }

        $sortOrders = $stateClass::getStatesSortOrder($model);

        // Create CASE WHEN statement for custom ordering
        $cases = collect($sortOrders)
            ->map(function ($order, $morphClass) use ($attribute) {
                $morphClass = addslashes($morphClass);

                return "WHEN `$attribute` = '$morphClass' THEN $order";
            })
            ->join(' ');

        $orderByExpression = "CASE $cases ELSE 999 END";

        return $query->orderByRaw("$orderByExpression $direction");
    }

    /**
     * Extract the base state class from cast definition
     * Handles both Spatie's default cast and FlexibleStateCast
     */
    protected function extractStateClass(?string $cast): ?string
    {
        if (! $cast) {
            return null;
        }

        // If using FlexibleStateCast, extract the state class from the parameter
        // Format: "RoBYCoNTe\FilamentFlow\Casts\FlexibleStateCast:App\States\Order\OrderState"
        if (str_contains($cast, 'FlexibleStateCast:')) {
            $parts = explode(':', $cast);

            return $parts[1] ?? null;
        }

        return $cast;
    }

    abstract public function getAttribute(?Model $model): string;
}
