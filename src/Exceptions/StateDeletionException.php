<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;

class StateDeletionException extends Exception
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('Cannot delete state with existing transitions. Remove transitions first.'));
    }
}
