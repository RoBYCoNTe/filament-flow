<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class ConditionEvaluator
{
    /**
     * Evaluate an array of conditions against a model.
     * All conditions must pass (AND logic).
     *
     * @param  array|null  $conditions  JSON-decoded conditions array
     *
     * Each condition: {"field": "assignmentType.name", "operator": "in", "value": ["Compatibilità"]}
     *
     * Supported operators: =, !=, in, not_in, >, <, >=, <=, is_null, is_not_null, contains
     */
    public function evaluate(Model $model, ?array $conditions): bool
    {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (! $this->evaluateCondition($model, $condition)) {
                return false;
            }
        }

        return true;
    }

    protected function evaluateCondition(Model $model, array $condition): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? $condition['values'] ?? null;

        if (! $field) {
            return true;
        }

        $modelValue = $this->resolveFieldValue($model, $field);

        return match ($operator) {
            '=' => $modelValue == $value,
            '!=' => $modelValue != $value,
            '>' => $modelValue > $value,
            '<' => $modelValue < $value,
            '>=' => $modelValue >= $value,
            '<=' => $modelValue <= $value,
            'in' => is_array($value) && in_array($modelValue, $value),
            'not_in' => is_array($value) && ! in_array($modelValue, $value),
            'is_null' => $modelValue === null,
            'is_not_null' => $modelValue !== null,
            'contains' => is_string($modelValue) && str_contains($modelValue, (string) $value),
            default => true,
        };
    }

    /**
     * Resolve a field value from a model, supporting dot notation for relations.
     * Example: "assignmentType.name" resolves to $model->assignmentType->name
     */
    protected function resolveFieldValue(Model $model, string $field): mixed
    {
        if (! str_contains($field, '.')) {
            return $model->{$field};
        }

        try {
            $segments = explode('.', $field);
            $current = $model;

            foreach ($segments as $segment) {
                if ($current === null) {
                    return null;
                }

                $current = $current->{$segment};
            }

            return $current;
        } catch (Throwable) {
            return null;
        }
    }
}
