<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * What a host implements so the requester can attach documents to a request: it keeps the files and
 * hands back an identifier for each, which is what the history records.
 *
 * The package never touches a disk. Optional: when nothing is bound the dialog of a request takes
 * no attachments.
 */
interface StoresRequestAttachments
{
    /**
     * Keeps one file for a request about to be opened on the record.
     *
     * @return int|string the identifier the request records
     */
    public function store(Model $record, string $transitionName, UploadedFile $file): int|string;

    /**
     * Forgets files that were kept for a request that did not open after all.
     *
     * @param  list<int|string>  $ids
     */
    public function discard(array $ids): void;

    /**
     * The documents a request carries, ready to be handed to whoever reads the request: what to
     * call each one and where to get it. The host decides what a reader may open, so an
     * identifier that is not the record's — or not there any more — is left out.
     *
     * @param  list<int|string>  $ids
     * @return list<array{id: int|string, name: string, url: string}>
     */
    public function documents(Model $record, array $ids): array;
}
