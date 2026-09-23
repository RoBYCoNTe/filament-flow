<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;

/**
 * Thrown when a state cannot be deleted because something still refers to it.
 */
class StateDeletionException extends Exception
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('Cannot delete state with existing transitions. Remove transitions first.'));
    }
}
