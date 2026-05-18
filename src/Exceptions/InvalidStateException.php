<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;

class InvalidStateException extends Exception
{
    public function __construct(string $message = 'Current state is not a valid State instance')
    {
        parent::__construct($message);
    }
}
