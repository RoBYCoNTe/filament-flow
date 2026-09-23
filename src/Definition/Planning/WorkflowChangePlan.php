<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Planning;

use RoBYCoNTe\FilamentFlow\Definition\Enums\MutationClass;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowDefinition;
use RoBYCoNTe\FilamentFlow\Models\Workflow;

/**
 * What applying a definition would do: the changes, the conflicts, and whether it may be
 * applied at all.
 *
 * A clean plan — `isClean()` — means the database already matches the declaration. That is what
 * makes applying the same definition twice harmless, and why a second run reporting "0 changes"
 * is a statement about the database rather than about the work done.
 */
final class WorkflowChangePlan
{
    /**
     * @param  list<WorkflowChange>  $changes
     * @param  list<array{code:string,message:string,key:string,context?:array<string,mixed>}>  $conflicts
     */
    public function __construct(
        public readonly WorkflowDefinition $definition,
        public readonly ?Workflow $existing,
        public readonly array $changes,
        public readonly array $conflicts,
    ) {}

    public function isClean(): bool
    {
        return $this->changes === [] && $this->existing !== null;
    }

    public function isCreate(): bool
    {
        return $this->existing === null;
    }

    public function hasBlockingConflicts(): bool
    {
        return $this->conflicts !== [];
    }

    public function isApplicable(): bool
    {
        return ! $this->hasBlockingConflicts();
    }

    public function mutationClass(): ?MutationClass
    {
        $rank = [MutationClass::Safe->value => 0, MutationClass::Additive->value => 1, MutationClass::Breaking->value => 2];
        $max = null;

        foreach ($this->changes as $change) {
            if ($max === null || $rank[$change->mutationClass->value] > $rank[$max->value]) {
                $max = $change->mutationClass;
            }
        }

        return $max;
    }

    /** @return list<WorkflowChange> */
    public function changesOf(string $type): array
    {
        return array_values(array_filter($this->changes, static fn (WorkflowChange $c): bool => $c->type === $type));
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'workflow' => $this->definition->getName(),
            'existing_id' => $this->existing?->id,
            'is_create' => $this->isCreate(),
            'is_clean' => $this->isClean(),
            'is_applicable' => $this->isApplicable(),
            'mutation_class' => $this->mutationClass()?->value,
            'changes' => array_map(static fn (WorkflowChange $c): array => $c->toArray(), $this->changes),
            'conflicts' => $this->conflicts,
        ];
    }

    public function summary(): string
    {
        if ($this->isClean()) {
            return 'No changes.';
        }

        return sprintf('%d change(s), %d conflict(s).', count($this->changes), count($this->conflicts));
    }
}
