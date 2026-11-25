<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\AccessControlGate;

/**
 * The historical answer: the administrators and the super administrators deal out the work.
 * It is what the panel uses when the host names no other gate.
 */
final class AdminAccessGate implements AccessControlGate
{
    public function canManage(?Authenticatable $user, ?Model $record): bool
    {
        if ($user === null) {
            return false;
        }

        return (method_exists($user, 'isAdmin') && $user->isAdmin())
            || (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin());
    }
}
