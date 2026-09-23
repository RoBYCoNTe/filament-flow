<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * What a host implements to evaluate the formulas of a condition: given the record and the
 * expression, it answers whether the condition holds and with which message.
 */
interface FormulaConditionProvider
{
    /**
     * Evaluate a formula expression against a model.
     *
     * $data carries the values that are not persisted yet (the live form state
     * during validation), so an expression can reference them without waiting
     * for a save. Honest providers expose them as a `form` variable.
     *
     * @param  array<string,mixed>  $data
     * @return bool true if the condition is satisfied, false otherwise
     */
    public function evaluate(string $expression, Model $model, array $data = []): bool;

    /**
     * Interpolate a message template against a model.
     * Used to produce the human-readable error when evaluate() returns false.
     *
     * @param  array<string,mixed>  $data
     */
    public function interpolate(string $template, Model $model, array $data = []): string;
}
