<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;

class ActionNotFoundException extends Exception
{
    public function __construct(public readonly string $actionName, ?string $message = null)
    {
        parent::__construct($message ?? "Action '{$actionName}' not found for current state.");
    }
}
