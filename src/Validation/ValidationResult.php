<?php

namespace RoBYCoNTe\FilamentFlow\Validation;

/**
 * Outcome of a validation pass: one entry per field path, ready to be attached
 * to a form component (or listed in a summary when the field has no component).
 */
final class ValidationResult
{
    /**
     * @param  array<string, list<string>>  $errors  field path => messages
     * @param  array<string, string>  $labels  field path => human readable label
     */
    public function __construct(
        private readonly array $errors = [],
        private readonly array $labels = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->errors === [];
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Flat list of errors, in field order: what a summary sidebar renders.
     *
     * @return list<array{path:string,label:string,message:string}>
     */
    public function messages(): array
    {
        $messages = [];

        foreach ($this->errors as $path => $fieldMessages) {
            foreach ($fieldMessages as $message) {
                $messages[] = [
                    'path' => $path,
                    'label' => $this->labelFor($path),
                    'message' => $message,
                ];
            }
        }

        return $messages;
    }

    public function has(string $path): bool
    {
        return isset($this->errors[$path]);
    }

    /** @return list<string> */
    public function messagesFor(string $path): array
    {
        return $this->errors[$path] ?? [];
    }

    public function labelFor(string $path): string
    {
        return $this->labels[$path] ?? $this->humanize($path);
    }

    /** Errors of a nested path, keyed by their prefix group (`costs` => [...]) */
    public function count(): int
    {
        return array_sum(array_map('count', $this->errors));
    }

    /** Path of the first error, or null when the result is empty. */
    public function firstErrorPath(): ?string
    {
        return array_key_first($this->errors);
    }

    private function humanize(string $path): string
    {
        $segments = explode('.', $path);
        $last = (string) end($segments);

        return ucfirst(str_replace('_', ' ', $last));
    }
}
