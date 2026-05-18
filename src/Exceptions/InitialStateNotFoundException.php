<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;

class InitialStateNotFoundException extends Exception
{
    public function __construct(string $message = 'No initial state defined for workflow')
    {
        parent::__construct($message);
    }
}
