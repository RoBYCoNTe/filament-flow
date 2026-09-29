<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

/**
 * What a record implements to say how it reads in a sentence: the code a notification puts
 * beside the event, so the reader knows which file it is about without opening it.
 *
 * A record that answers `null` (or does not implement this) falls back to the engine's own
 * reading — the title the host gave it, then its code, then nothing.
 */
interface HasWorkflowLabel
{
    /**
     * The code of the record: a protocol number, a reference, a short id. Null when the host
     * has none to give.
     */
    public function workflowLabel(): ?string;
}
