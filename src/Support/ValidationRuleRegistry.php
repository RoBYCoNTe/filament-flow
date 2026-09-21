<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Closure;
use RoBYCoNTe\FilamentFlow\Exceptions\UnknownValidationRuleException;

/**
 * Registry of named validation rules, reusable in any rule declaration
 * (`rules: ['fiscal_code_checksum']`) and by host applications.
 *
 * Rules are Laravel validation closures:
 *   function (string $attribute, mixed $value, Closure $fail): void
 */
class ValidationRuleRegistry
{
    /** @var array<string, Closure> */
    private array $rules = [];

    /**
     * Register a rule by name.
     *
     * A declaration can carry a parameter — `name:anything:after:the:colon` —
     * which reaches the closure as its fourth argument: it is what lets a rule
     * say which document of a set is missing, instead of talking about the field
     * as a whole.
     */
    public function register(string $name, Closure $rule): void
    {
        $this->rules[$name] = $rule;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->rules);
    }

    /**
     * @throws UnknownValidationRuleException
     */
    public function resolve(string $name): Closure
    {
        if (! $this->has($name)) {
            throw new UnknownValidationRuleException("Unknown validation rule [{$name}].");
        }

        return $this->rules[$name];
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->rules);
    }
}
