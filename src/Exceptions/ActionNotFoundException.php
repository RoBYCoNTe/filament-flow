<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;

/**
 * Thrown when an action is asked of something that does not offer it.
 */
class ActionNotFoundException extends Exception
{
    public function __construct(public readonly string $actionName, ?string $message = null)
    {
        parent::__construct($message ?? "Action '{$actionName}' not found for current state.");
    }
}
