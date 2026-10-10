<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Lets the host take away from the permissions of a state what the data of the record takes away.
 *
 * The permissions of a state are the same for every record in it, so they cannot say that a field
 * is out of the picture *because of what another field holds*. The host can: it is asked once per
 * validation pass, with the values the pass runs on (the live form, or the stored ones), and
 * returns the permissions with those fields marked `visible => false`. A field that is not visible
 * is neither required nor validated, whichever source the rule comes from.
 *
 * Optional: when nothing is bound, the permissions are exactly those of the state. It can only
 * narrow — a host that hands back a field the state hides is not honoured.
 */
interface NarrowsFieldPermissions
{
    /**
     * @param  array<string,mixed>  $data  the values the validation runs on, nested by path
     * @param  array<string,array{visible:bool,readonly:bool,locked:bool,required:bool,validation:array<int,string>|null}>  $permissions  what the state says, by path
     * @return array<string,array{visible:bool,readonly:bool,locked:bool,required:bool,validation:array<int,string>|null}>
     */
    public function narrow(Model $record, ?Model $user, array $data, array $permissions): array;
}
