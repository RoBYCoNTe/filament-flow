<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Support\CompletionPayload;

/**
 * What a host implements to feed the formula editor: the variables and the functions of one
 * scope, for the context being edited.
 */
interface FormulaCompletionProvider
{
    public function getCompletions(?Model $context): CompletionPayload;
}
