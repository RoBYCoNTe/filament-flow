<?php

namespace RoBYCoNTe\FilamentFlow\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised when the owner of a record hands it over: who held it, who holds it now, and what
 * the old owner kept — nothing, the role of an observer, the role of a collaborator.
 */
class WorkflowOwnerChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Model $record,
        public readonly ?int $fromUserId,
        public readonly int $toUserId,
        public readonly string $retention = 'none',
        public readonly ?Model $actor = null,
        public readonly ?string $note = null,
    ) {}
}
