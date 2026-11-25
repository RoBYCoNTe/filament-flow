<?php

namespace RoBYCoNTe\FilamentFlow\Support;

/**
 * The person a record is handed to, as a `TransferTargetResolver` answers: who they are, and
 * whatever the host wants written beside the handover (the act that asked for it, how the person
 * came to exist). The package keeps the metadata with the change and with its event, and reads
 * nothing of it.
 */
final class TransferTarget
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $metadata = [],
    ) {}
}
