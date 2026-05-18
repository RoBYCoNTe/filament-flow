<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;

class ConditionNotMetException extends Exception
{
    public function __construct(public readonly string $actionName, ?string $message = null)
    {
        parent::__construct($message ?? "Conditions not met for action '{$actionName}'.");
    }
}
