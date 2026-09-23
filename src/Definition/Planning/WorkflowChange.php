<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Planning;

use RoBYCoNTe\FilamentFlow\Definition\Enums\MutationClass;

/**
 * One difference between the workflow stored in the database and the one the declaration asks
 * for: what changed, on which key, and how serious it is.
 */
final class WorkflowChange
{
    public function __construct(
        public readonly string $type,
        public readonly string $key,
        public readonly MutationClass $mutationClass,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'change' => $this->type,
            'key' => $this->key,
            'mutation_class' => $this->mutationClass->value,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }
}
