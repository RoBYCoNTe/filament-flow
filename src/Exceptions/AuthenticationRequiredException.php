<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;

/**
 * Thrown when a transition is attempted with nobody authenticated: the rules of the state have
 * to be evaluated for somebody, and guessing is not an option. It carries the message the host
 * wants a person to read.
 */
class AuthenticationRequiredException extends Exception
{
    public function __construct(string $message = 'User not authenticated')
    {
        parent::__construct($message);
    }
}
