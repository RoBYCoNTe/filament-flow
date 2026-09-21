<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;

/**
 * Applying the rules that belong to a state or a transition.
 *
 * Three sources decide what a field must respect: the permissions of the state (required,
 * read-only, locked), the rules the transition itself declares, and whatever the host adds.
 * They are collected here in the order they win.
 */
trait AppliesValidationRules
{
    /**
     * @param  array<string,array<string,mixed>>  $permissions
     * @param  array<string,list<string>>  $errors
     *
     * @param-out  array<string,list<string>>  $errors
     *
     * @param  array<string,string>  $labels
     *
     * @param-out  array<string,string>  $labels
     */
    private function applyPermissionRules(
        Model $record,
        array $data,
        array $permissions,
        array &$errors,
        array &$labels,
    ): void {
        foreach ($permissions as $path => $permission) {
            if (($permission['visible'] ?? true) === false) {
                continue;
            }

            // `required` is a rule like any other: the state declares it, the
            // engine runs it through the same validator as every other rule.
            $rules = ($permission['required'] ?? false) === true ? ['required'] : [];
            $rules = array_merge($rules, array_values(array_filter((array) ($permission['validation'] ?? []))));

            if ($rules !== []) {
                $this->runRules($record, $data, (string) $path, $rules, null, $errors, $labels, $permissions);
            }
        }
    }

    /**
     * @param  array<string,array<string,mixed>>  $permissions
     * @param  array<string,list<string>>  $errors
     *
     * @param-out  array<string,list<string>>  $errors
     *
     * @param  array<string,string>  $labels
     *
     * @param-out  array<string,string>  $labels
     */
    private function applyTransitionRules(
        Model $record,
        array $data,
        WorkflowTransition $transition,
        array $permissions,
        array &$errors,
        array &$labels,
    ): void {
        foreach ($transition->validationRuleConfigs() as $config) {
            $path = $config['field_name'];

            if (($permissions[$path]['visible'] ?? true) === false) {
                continue;
            }

            if ($config['condition'] !== null && ! $this->evaluateFormula($config['condition'], $record, $data)) {
                continue;
            }

            if ($config['label'] !== null) {
                $labels[$path] = $config['label'];
            }

            if ($config['rule_type'] === 'expression') {
                $expression = $config['rules'][0] ?? '';

                if ($expression === '' || $this->evaluateFormula($expression, $record, $data)) {
                    continue;
                }

                $errors[$path][] = $config['custom_message'] !== null
                    ? $this->interpolate($config['custom_message'], $record, $data)
                    : (string) __('The value of :field is not valid.', ['field' => $this->labelOf($permissions, $path, $config['label'])]);

                continue;
            }

            $this->runRules(
                $record,
                $data,
                $path,
                $config['rules'],
                $config['custom_message'],
                $errors,
                $labels,
                $permissions,
                $config['label'],
            );
        }
    }

    /**
     * @param  array<string,array<string,mixed>>  $permissions
     * @param  array<string,list<string>>  $errors
     *
     * @param-out  array<string,list<string>>  $errors
     *
     * @param  array<string,string>  $labels
     *
     * @param-out  array<string,string>  $labels
     */
    private function applyHostRules(
        Model $record,
        ?Model $user,
        array $data,
        array $permissions,
        array &$errors,
        array &$labels,
    ): void {
        foreach ($this->hostSources() as $source) {
            foreach ($source->rulesFor($record, $user) as $path => $rules) {
                $path = (string) $path;

                if (($permissions[$path]['visible'] ?? true) === false) {
                    continue;
                }

                $this->runRules($record, $data, $path, array_values((array) $rules), null, $errors, $labels, $permissions);
            }
        }
    }

    /**
     * Runs Laravel rules and named registry rules for one path.
     *
     * @param  list<string>  $rules
     * @param  array<string,array<string,mixed>>  $permissions
     * @param  array<string,list<string>>  $errors
     *
     * @param-out  array<string,list<string>>  $errors
     *
     * @param  array<string,string>  $labels
     *
     * @param-out  array<string,string>  $labels
     */
    private function runRules(
        Model $record,
        array $data,
        string $path,
        array $rules,
        ?string $customMessage,
        array &$errors,
        array &$labels,
        array $permissions,
        ?string $label = null,
    ): void {
        $value = $this->value($record, $data, $path);

        if ($label !== null) {
            $labels[$path] = $label;
        }

        $laravel = [];

        foreach ($rules as $rule) {
            $rule = trim((string) $rule);

            if ($rule === '') {
                continue;
            }

            $name = Str::before($rule, ':');

            // A rule can be a formula over the value itself (`expression:...`):
            // it is how a host application declares conditions the Laravel rules
            // cannot express (the requirements of a set of attachments, for
            // instance). The value travels in the data as `value`.
            if ($name === 'expression') {
                $formula = trim(Str::after($rule, ':'));

                if ($formula === '' || $this->evaluateFormula($formula, $record, ['value' => $value] + $data)) {
                    continue;
                }

                $errors[$path][] = (string) __('The value of :field is not valid.', [
                    'field' => $this->labelOf($permissions, $path, $label),
                ]);

                continue;
            }

            if ($this->registry->has($name)) {
                // A named rule is a closure: it is skipped, like every rule, when
                // the value is absent and the field is not required.
                if ($value === self::MISSING || $value === null) {
                    continue;
                }

                $fail = function (string $message) use (&$errors, $path): void {
                    $errors[$path][] = $message;
                };

                // Everything after the first `:` travels to the rule as it is
                // written: `attachment_uploaded:key|Label`.
                $parameters = Str::contains($rule, ':') ? Str::after($rule, ':') : null;

                ($this->registry->resolve($name))($path, $value, $fail, $parameters);

                continue;
            }

            $laravel[] = $rule;
        }

        if ($laravel === []) {
            return;
        }

        $key = str_replace(['.', '*'], ' ', $path);

        $validator = Validator::make(
            [$key => $value === self::MISSING ? null : $value],
            [$key => $laravel],
            $customMessage !== null ? [$key => $customMessage] : [],
            [$key => $this->labelOf($permissions, $path, $label)],
        );

        foreach ($validator->errors()->get($key) as $message) {
            $errors[$path][] = (string) $message;
        }
    }
}
