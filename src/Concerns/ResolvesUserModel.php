<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoBYCoNTe\FilamentFlow\Support\UserModel;

/**
 * Models that point at the host user model (assignments, transition history,
 * notification logs, involvement tracking).
 *
 * The resolution logic lives in {@see UserModel}: the package must not import a
 * concrete application class.
 */
trait ResolvesUserModel
{
    /** @return class-string<Model> */
    protected function getUserModel(): string
    {
        return UserModel::resolve();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo($this->getUserModel());
    }
}
