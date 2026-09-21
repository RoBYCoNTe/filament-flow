<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Illuminate\Database\Eloquent\Model;

interface FieldListProviderInterface
{
    /**
     * Returns the list of field keys available for this context model.
     *
     * @return array<string>
     */
    public function getFields(Model $context): array;
}
