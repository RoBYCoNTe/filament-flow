<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\AccessControlGate;

/**
 * Only the super administrators deal out the work: plain administrators read the panel, and
 * nothing more. It is the gate behind `superAdminOnly`.
 */
final class SuperAdminAccessGate implements AccessControlGate
{
    public function canManage(?Authenticatable $user, ?Model $record): bool
    {
        return $user !== null && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }
}
