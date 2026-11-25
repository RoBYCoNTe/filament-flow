<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\Gates;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\AccessControlGate;

/**
 * A gate of the host: the reviewers deal out the work, the administrators do not. It remembers
 * the record it was asked about, so a test can tell the panel handed it over.
 */
final class ReviewerAccessGate implements AccessControlGate
{
    public static ?Model $lastRecord = null;

    public function canManage(?Authenticatable $user, ?Model $record): bool
    {
        self::$lastRecord = $record;

        return $user !== null && $user->role === 'reviewer';
    }
}
