<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;

/**
 * Thrown when no workflow matches the model, the state column and the tenant.
 */
class WorkflowNotFoundException extends Exception
{
    public function __construct(public readonly string $modelClass, ?string $message = null)
    {
        parent::__construct($message ?? "No active workflow found for {$modelClass}");
    }
}
