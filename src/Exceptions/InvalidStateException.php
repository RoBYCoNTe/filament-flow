<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;

/**
 * Thrown when a state name is not one of the states of the workflow — the guard against a
 * transition to a state nobody declared.
 */
class InvalidStateException extends Exception
{
    public function __construct(string $message = 'Current state is not a valid State instance')
    {
        parent::__construct($message);
    }
}
