<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorContract;
use RoBYCoNTe\FilamentFlow\Validation\ValidationResult;

/**
 * Thrown when a transition (or an in-state action) runs against data that does
 * not satisfy the rules of the target state.
 *
 * Extends ValidationException so every Laravel/Filament consumer already knows
 * how to render it: the messages are keyed by form path, exactly as the form
 * component names them.
 */
class WorkflowValidationException extends ValidationException
{
    public function __construct(public readonly ValidationResult $result)
    {
        parent::__construct(self::validatorFor($result));

        $this->status = 422;

        // `getMessage()` carries the actual rule failures, not just "invalid
        // data": logs, API responses and the simulator transcript need them.
        $this->message = $this->errorSummary();
    }

    /**
     * A validator carrying the errors of the engine.
     *
     * The framework reads `$exception->validator->errors()` (Livewire merges them
     * into the component error bag before rendering, Filament paints the fields
     * with them): an empty validator would silently drop the messages, leaving
     * the form without a single red field.
     */
    private static function validatorFor(ValidationResult $result): ValidatorContract
    {
        $validator = Validator::make([], []);

        foreach ($result->errors() as $path => $messages) {
            foreach ($messages as $message) {
                $validator->errors()->add((string) $path, $message);
            }
        }

        return $validator;
    }

    /** `field: message` for every error, in field order. */
    public function errorSummary(): string
    {
        return collect($this->result->messages())
            ->map(static fn (array $error): string => $error['path'].': '.$error['message'])
            ->implode(' ');
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->result->errors();
    }

    /**
     * Flat list of messages with their field label: what an error summary shows.
     *
     * @return list<array{path:string,label:string,message:string}>
     */
    public function summary(): array
    {
        return $this->result->messages();
    }

    public function firstErrorPath(): ?string
    {
        return $this->result->firstErrorPath();
    }

    /**
     * Same messages, prefixed with a form state path (`data.`), which is how a
     * Filament form expects them.
     *
     * @return array<string, list<string>>
     */
    public function errorsWithPathPrefix(string $statePath): array
    {
        if ($statePath === '') {
            return $this->errors();
        }

        return array_combine(
            array_map(static fn (string $path): string => $statePath.'.'.$path, array_keys($this->errors())),
            array_values($this->errors()),
        );
    }
}
