<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;

class InvalidComponentException extends Exception
{
    public function __construct(public readonly string $fieldName, ?string $message = null)
    {
        parent::__construct($message ?? "Component for field '{$fieldName}' does not support readonly or disabled state.");
    }
}
