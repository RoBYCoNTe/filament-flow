<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Planning;

use RoBYCoNTe\FilamentFlow\Definition\Enums\MutationClass;

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
