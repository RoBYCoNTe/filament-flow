<?php

namespace RoBYCoNTe\FilamentFlow\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised when a record leaves a state.
 */
class StateExited
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Model $record,
        public readonly string $state,
        public readonly ?Model $user = null,
    ) {}
}
