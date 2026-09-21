<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Application side of the validation engine: the rules declared on the fields
 * of a record (the scheme schema of a bando, in this project).
 *
 * The package never knows where the rules come from: it only runs them, together
 * with the ones the workflow itself declares.
 */
interface FieldRuleSource
{
    /**
     * Rules keyed by form path. A rule is either a Laravel rule (`gte:60`) or the
     * name of a rule registered in the ValidationRuleRegistry
     * (`fiscal_code_checksum`).
     *
     * @return array<string, list<string>>
     */
    public function rulesFor(Model $record, ?Model $user): array;
}
