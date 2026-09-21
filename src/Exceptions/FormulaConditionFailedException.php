<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;

class FormulaConditionFailedException extends Exception
{
    public function __construct(string $message = 'Formula condition not met.')
    {
        parent::__construct($message);
    }
}
