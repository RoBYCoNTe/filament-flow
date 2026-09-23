<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;

/**
 * Thrown when the conditions of a transition do not hold for the record.
 */
class ConditionNotMetException extends Exception
{
    public function __construct(public readonly string $actionName, ?string $message = null)
    {
        parent::__construct($message ?? "Conditions not met for action '{$actionName}'.");
    }
}
