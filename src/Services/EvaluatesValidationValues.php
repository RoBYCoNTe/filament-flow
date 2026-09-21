<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RoBYCoNTe\FilamentFlow\Contracts\FieldRuleSource;
use RoBYCoNTe\FilamentFlow\Contracts\HasFieldLabels;
use RoBYCoNTe\FilamentFlow\Support\FormulaConditionRegistry;

/**
 * Reading the values a rule is checked against.
 *
 * A rule looks at a field of the record, at an override typed in a form, at a label worth
 * showing to a person, and at the formulas and the templates a host declares: the same four
 * readings, done the same way for every rule.
 */
trait EvaluatesValidationValues
{
    /**
     * Value of a path: the form state first, then the record (an attribute, or
     * the `form_data` column of a JSON-backed model).
     *
     * @param  array<string,mixed>  $data
     */
    private function value(Model $record, array $data, string $path): mixed
    {
        return data_get($data, $path, self::MISSING);
    }

    /**
     * Values stored on the record: its own attributes, plus the JSON column that
     * holds the scheme data of a JSON-backed model (`form_data`).
     *
     * @return array<string,mixed>
     */
    private function recordData(Model $record): array
    {
        $data = $record->attributesToArray();
        $formData = $record->getAttribute('form_data');

        return is_array($formData) ? array_replace($data, $formData) : $data;
    }

    /**
     * @param  array<string,array<string,mixed>>  $permissions
     */
    private function labelOf(array $permissions, string $path, ?string $explicit = null): string
    {
        if ($explicit !== null && $explicit !== '') {
            return $explicit;
        }

        $configured = $permissions[$path]['label'] ?? null;

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        // The record can know the labels of its own fields (a scheme schema, for
        // instance): the message a person reads carries that label.
        if ($this->record instanceof HasFieldLabels) {
            $label = $this->record->fieldLabel($path);

            if (is_string($label) && trim($label) !== '') {
                return $label;
            }
        }

        return ucfirst(str_replace('_', ' ', (string) Str::afterLast($path, '.')));
    }

    /** @return list<FieldRuleSource> */
    private function hostSources(): array
    {
        $sources = [];

        foreach ((array) config('filament-flow.validation.rule_sources', []) as $source) {
            if (is_string($source) && class_exists($source)) {
                $sources[] = app($source);
            }

            if ($source instanceof FieldRuleSource) {
                $sources[] = $source;
            }
        }

        return $sources;
    }

    private function evaluateFormula(string $expression, Model $record, array $data): bool
    {
        $registry = app(FormulaConditionRegistry::class);

        if (! $registry->has()) {
            return true;
        }

        return $registry->get()->evaluate($expression, $record, $data);
    }

    private function interpolate(string $template, Model $record, array $data): string
    {
        $registry = app(FormulaConditionRegistry::class);

        if (! $registry->has()) {
            return $template;
        }

        return $registry->get()->interpolate($template, $record, $data);
    }
}
