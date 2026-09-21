<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Support\CompletionPayload;

interface FormulaCompletionProvider
{
    public function getCompletions(?Model $context): CompletionPayload;
}
