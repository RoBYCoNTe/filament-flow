<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use RoBYCoNTe\FilamentFlow\Presentation\FieldPresentation;

/**
 * Implemented by models that know how their own fields read.
 *
 * A field is a path into the data of the record (`intervention.amount_requested`): the host
 * owns the words and the shapes — the label, whether the value is money, a date, a list of
 * files, the section it belongs to — and the engine only draws it. Returning `null` says the
 * path is not a field of this record, and the engine falls back to its generic reading.
 */
interface HasFieldPresentation
{
    /**
     * How one of this record's fields reads, or null when the path is not a field it knows.
     */
    public function fieldPresentation(string $path, mixed $value): ?FieldPresentation;
}
