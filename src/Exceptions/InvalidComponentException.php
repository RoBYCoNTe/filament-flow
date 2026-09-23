<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;

/**
 * Thrown when a component is asked for something it cannot give, in the shape the package
 * expects.
 */
class InvalidComponentException extends Exception
{
    public function __construct(public readonly string $fieldName, ?string $message = null)
    {
        parent::__construct($message ?? "Component for field '{$fieldName}' does not support readonly or disabled state.");
    }
}
