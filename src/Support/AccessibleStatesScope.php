<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Services\ScopesAccessibleRecords;

/**
 * The clauses of "who may see what", written once.
 *
 * The engine narrows a query with them ({@see ScopesAccessibleRecords}),
 * and so does a host that keeps a list of its own — the platform of the calls above all, whose
 * list **must not disagree** with the engine. It did once: the rows an assignment handed a person
 * were missing from the list, because the clauses had been written twice and only one copy knew
 * about assignments.
 *
 * The shape: the states a role opens (everybody), the ones an assignment opens (the people the
 * work was handed to), the ones an override grants (in any state) — all of it held under a
 * denial, which wins over every opening.
 *
 * `$ownerField` says how the owner of a record is read: the engine leaves it out (the per-row
 * check answers for ownership), while a host whose list has no per-row check passes the column
 * the owner lives in, so the records a person owns enter the states that ask for one.
 */
final class AccessibleStatesScope
{
    public static function apply(
        Builder $query,
        AccessibleStates $states,
        ?Model $user,
        string $accessType = 'view',
        string $stateColumn = 'state',
        ?string $ownerField = null,
    ): Builder {
        $withAssignments = self::withAssignments($query, $user);

        return $query->where(function (Builder $outer) use ($states, $user, $accessType, $stateColumn, $ownerField, $withAssignments): void {
            $outer->where(function (Builder $open) use ($states, $user, $accessType, $stateColumn, $ownerField, $withAssignments): void {
                $hasOpening = false;

                if ($states->free !== []) {
                    $open->whereIn($stateColumn, $states->free);
                    $hasOpening = true;
                }

                if ($states->assigned !== [] && $user !== null) {
                    $open->orWhere(function (Builder $assigned) use ($states, $user, $stateColumn, $ownerField, $withAssignments): void {
                        $assigned->whereIn($stateColumn, $states->assigned);

                        // A record that cannot carry assignments has no way to hand the rows to
                        // someone: the state alone opens them, and the per-row check answers for
                        // the ownership (see `AccessibleStatesScope` — the promise the host and
                        // the engine keep together).
                        if (! $withAssignments) {
                            return;
                        }

                        $assigned->where(function (Builder $handed) use ($user, $ownerField): void {
                            if ($ownerField !== null) {
                                $handed->where($ownerField, $user->getKey())
                                    ->orWhere(fn (Builder $also): Builder => self::assignedTo($also, $user));
                            } else {
                                self::assignedTo($handed, $user);
                            }
                        });
                    });
                    $hasOpening = true;
                }

                // An override grants the record in any state, not only in the ones that ask for
                // an assignee.
                if ($withAssignments && $user !== null) {
                    $open->orWhere(fn (Builder $granted): Builder => self::granted($granted, $user, $accessType));
                    $hasOpening = true;
                }

                if (! $hasOpening) {
                    $open->whereRaw('1 = 0');
                }
            });

            if ($withAssignments && $user !== null) {
                self::denied($outer, $user, $accessType);
            }
        });
    }

    /** The rows an assignment has handed to this person, whatever its type. */
    public static function assignedTo(Builder $query, Model $user): Builder
    {
        return $query->whereHas('assignments', fn (Builder $assignment): Builder => $assignment->where('user_id', $user->getKey()));
    }

    /** The rows an assignment has opened to this person, whatever the rules of their state. */
    public static function granted(Builder $query, Model $user, string $accessType = 'view'): Builder
    {
        return $query->whereHas('assignments', fn (Builder $assignment): Builder => $assignment
            ->where('user_id', $user->getKey())
            ->where('override_'.$accessType, true));
    }

    /**
     * The rows this person has been shut out of: a refusal holds over every opening — the rules
     * of the state and a permission of the same kind alike.
     */
    public static function denied(Builder $query, Model $user, string $accessType = 'view'): Builder
    {
        return $query->whereDoesntHave('assignments', fn (Builder $assignment): Builder => $assignment
            ->where('user_id', $user->getKey())
            ->where('override_'.$accessType, false));
    }

    /** Whether the records of this query can carry assignments at all. */
    public static function withAssignments(Builder $query, ?Model $user): bool
    {
        return $user !== null && method_exists($query->getModel(), 'assignments');
    }
}
