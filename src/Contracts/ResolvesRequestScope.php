<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Tells which fields an open request lets the answering side change on a record.
 *
 * Optional: when nothing is bound, field permissions are exactly those of the state.
 */
interface ResolvesRequestScope
{
    /**
     * Whether the workflow of this record declares a request scope at all.
     *
     * Permissions are read field by field, so this has to be cheap: when it is false nothing else
     * is asked — no roles, no history.
     */
    public function declaresScope(Model $record): bool;

    /**
     * The scope that applies to this record for this user, or null when none does.
     *
     * `$effectiveRoles` are the roles the user acts with on this record (the static ones and the
     * virtual ones, `@owner`, `@assigned`): a request is answered by a role, so the resolver
     * compares it with them.
     *
     * @param  array<int,string>  $effectiveRoles
     * @return array{
     *     mode: 'exclusive'|'additive',
     *     paths: list<string>,
     *     whitelist: list<string>|null,
     *     universe: list<string>,
     * }|null
     */
    public function scopeFor(Model $record, ?Model $user, array $effectiveRoles = []): ?array;
}
