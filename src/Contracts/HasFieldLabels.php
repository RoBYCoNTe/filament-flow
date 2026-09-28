<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

/**
 * Implemented by models that know the labels of their own fields.
 *
 * The validation engine reports errors by field path, but the message a person
 * reads has to carry the label of the field ("Amount requested", not
 * "intervention.amount_requested"): the host application owns that knowledge, so
 * the engine asks it.
 */
interface HasFieldLabels
{
    /**
     * Label of a field path, or null when the path has no declared field.
     */
    public function fieldLabel(string $path): ?string;
}
