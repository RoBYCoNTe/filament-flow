<?php

namespace RoBYCoNTe\FilamentFlow\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransitionCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Model $record,
        public readonly string $from,
        public readonly string $to,
        public readonly ?Model $user = null,
        public readonly array $metadata = [],
    ) {}
}
