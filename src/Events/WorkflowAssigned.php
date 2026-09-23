<?php

namespace RoBYCoNTe\FilamentFlow\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised when somebody is assigned to a record.
 */
class WorkflowAssigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Model $record,
        public readonly Model $assignee,
        public readonly ?Model $assignedBy = null,
        public readonly string $assignmentType = 'primary',
    ) {}
}
