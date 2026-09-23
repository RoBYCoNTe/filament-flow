<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * What a host implements so the package knows which fields a formula may name: given a context
 * — an application, a call — it answers with the keys.
 */
interface FieldListProviderInterface
{
    /**
     * Returns the list of field keys available for this context model.
     *
     * @return array<string>
     */
    public function getFields(Model $context): array;
}
