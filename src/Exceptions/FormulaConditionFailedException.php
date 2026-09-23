<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;

/**
 * Thrown when a formula condition refuses: the expression was evaluated, and the message is the
 * one the host produced for it.
 */
class FormulaConditionFailedException extends Exception
{
    public function __construct(string $message = 'Formula condition not met.')
    {
        parent::__construct($message);
    }
}
