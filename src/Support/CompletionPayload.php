<?php

namespace RoBYCoNTe\FilamentFlow\Support;

/**
 * Payload returned by FormulaCompletionProvider and serialised to JSON
 * for the Monaco IntelliSense endpoint.
 *
 * $variables — top-level symbols available in the expression scope.
 *   Each entry: { name, kind, type, description, properties?, methods?, string_values? }
 *
 * $stringValues — named lists used for inside-string completions.
 *   E.g. ['states' => ['draft', 'submitted', 'approved']]
 */
final class CompletionPayload
{
    /**
     * @param  list<array{name:string,kind:string,type:string,description:string,properties?:list<array{name:string,kind:string,type:string}>,methods?:list<array{name:string,kind:string,signature:string,description:string}>}>  $variables
     * @param  array<string,list<string>>  $stringValues
     */
    public function __construct(
        public readonly array $variables = [],
        public readonly array $stringValues = [],
    ) {}

    public function toArray(): array
    {
        return [
            'variables' => $this->variables,
            'string_values' => $this->stringValues,
        ];
    }

    public static function empty(): self
    {
        return new self([], []);
    }
}
