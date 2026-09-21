<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Exceptions\FormulaConditionFailedException;
use RoBYCoNTe\FilamentFlow\Support\FormulaConditionRegistry;
use Throwable;

class ConditionEvaluator
{
    /**
     * Evaluate an array of conditions against a model.
     * All conditions must pass (AND logic).
     *
     * @param  array|null  $conditions  JSON-decoded conditions array
     *
     * Field condition: {"field": "assignmentType.name", "operator": "in", "value": ["Compatibilità"]}
     * Formula condition: {"type": "formula", "expression": "amount > 0", "message_template": "Amount must be positive."}
     *
     * Supported operators: =, !=, in, not_in, >, <, >=, <=, is_null, is_not_null, contains
     *
     * @throws FormulaConditionFailedException when a formula condition is not satisfied
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

    /**
     * @throws FormulaConditionFailedException
     */
    protected function evaluateCondition(Model $model, array $condition): bool
    {
        if (($condition['type'] ?? 'field') === 'formula') {
            return $this->evaluateFormulaCondition($model, $condition);
        }

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
     * @throws FormulaConditionFailedException when the formula evaluates to false
     */
    protected function evaluateFormulaCondition(Model $model, array $condition): bool
    {
        $expression = $condition['expression'] ?? '';
        $messageTemplate = $condition['message_template'] ?? $condition['message'] ?? 'Condition not met.';

        $registry = app(FormulaConditionRegistry::class);

        if (! $registry->has()) {
            return true;
        }

        $provider = $registry->get();

        if ($provider->evaluate($expression, $model)) {
            return true;
        }

        throw new FormulaConditionFailedException($provider->interpolate($messageTemplate, $model));
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
