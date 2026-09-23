<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Resolves the user model of the host application.
 *
 * The package never references a concrete user class: the configured
 * `filament-flow.user_model` wins, then Laravel's own auth provider. Hosts can
 * also point at a dedicated model (e.g. a `WorkflowUser` read model) without
 * touching the package code.
 */
final class UserModel
{
    /**
     * @return class-string<Model>
     *
     * @throws RuntimeException
     */
    public static function resolve(): string
    {
        $model = config('filament-flow.user_model') ?? config('auth.providers.users.model');

        if (! is_string($model) || $model === '') {
            throw new RuntimeException(
                'No user model configured: set filament-flow.user_model or config/auth.php providers.users.model.'
            );
        }

        return $model;
    }
}
