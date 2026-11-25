<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may deal out the work of a record: add and remove the people who work on it, change what
 * each one may do, hand the record over to someone else.
 *
 * The panel asks on every request, never once: a gate is named by its class and built by the
 * container each time, so what it answers follows the person and the record of the moment, and
 * a host can open the panel to more roles — or to some records only — without touching the
 * package.
 *
 * ```php
 * class OperatorsAccessGate implements AccessControlGate
 * {
 *     public function canManage(?Authenticatable $user, ?Model $record): bool
 *     {
 *         return $user?->hasAnyRole(['super_admin', 'review_operator']) ?? false;
 *     }
 * }
 * ```
 */
interface AccessControlGate
{
    /**
     * Whether the person manages the access of the record.
     *
     * @param  Authenticatable|null  $user  The person asking, null when nobody is signed in
     * @param  Model|null  $record  The record the panel talks about, null when it is not known yet
     */
    public function canManage(?Authenticatable $user, ?Model $record): bool;
}
