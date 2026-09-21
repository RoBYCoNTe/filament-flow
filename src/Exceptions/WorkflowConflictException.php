<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use RoBYCoNTe\FilamentFlow\Definition\Planning\WorkflowChangePlan;

/**
 * Thrown when a workflow plan has blocking conflicts. The full, machine-readable
 * plan is attached so callers can inspect it.
 */
class WorkflowConflictException extends \RuntimeException
{
    public function __construct(public readonly WorkflowChangePlan $plan)
    {
        $messages = array_map(
            static fn (array $c): string => '- ['.$c['code'].'] '.$c['message'],
            $plan->conflicts,
        );

        parent::__construct(sprintf(
            "Cannot apply workflow [%s]:\n%s",
            $plan->definition->getName(),
            implode("\n", $messages) ?: '- unknown conflict',
        ));
    }

    /** @return list<array{code:string,message:string,key:string}> */
    public function conflicts(): array
    {
        return $this->plan->conflicts;
    }
}
