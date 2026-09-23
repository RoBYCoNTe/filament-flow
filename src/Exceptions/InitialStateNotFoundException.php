<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;

/**
 * Thrown when a workflow declares no initial state, so nothing can say where a new record
 * starts.
 */
class InitialStateNotFoundException extends Exception
{
    public function __construct(string $message = 'No initial state defined for workflow')
    {
        parent::__construct($message);
    }
}
