<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;

class AuthenticationRequiredException extends Exception
{
    public function __construct(string $message = 'User not authenticated')
    {
        parent::__construct($message);
    }
}
