<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Who holds a record: the column the owner lives in is configured once
 * (`state_access.owner_field`, a `user_id` by default), and everything that has to read it —
 * the owner column, the export, a host that asks — comes through here.
 */
final class RecordOwner
{
    /** The column the owner lives in, as the package is configured. */
    public static function field(): string
    {
        $field = config('filament-flow.state_access.owner_field', 'user_id');

        return is_string($field) && $field !== '' ? $field : 'user_id';
    }

    /** The key of the owner of a record, when it has one. */
    public static function id(Model $record): int|string|null
    {
        $value = $record->{self::field()};

        return $value === null || $value === '' ? null : $value;
    }

    /**
     * Whether the record carries the owner column at all: a model that never had one cannot be
     * handed over, and the panel says so instead of writing a column nobody reads.
     */
    public static function exists(Model $record): bool
    {
        $field = self::field();

        return $record->isFillable($field)
            || array_key_exists($field, $record->getAttributes())
            || $record->hasAttributeMutator($field)
            || property_exists($record, $field);
    }

    /** The owner of a record, resolved to the user model, when the record has one. */
    public static function of(Model $record): ?Model
    {
        $id = self::id($record);

        if ($id === null) {
            return null;
        }

        // A relation the host already loaded (`with('user')`) is read from the record: a list
        // asks for the owner once per row, and one query per row is what makes a table slow.
        $relation = self::relationName();

        if ($relation !== null && $record->relationLoaded($relation)) {
            $loaded = $record->getRelation($relation);

            return $loaded instanceof Model ? $loaded : null;
        }

        $userModel = UserModel::resolve();

        /** @var Model|null $owner */
        $owner = $userModel::query()->find($id);

        return $owner;
    }

    /**
     * The relation a conventional owner column implies: `user_id` → `user`. Null when the
     * column does not name one (`owner`), and the owner has to be read by key.
     */
    public static function relationName(): ?string
    {
        $field = self::field();

        if (! str_ends_with($field, '_id') || $field === '_id') {
            return null;
        }

        return Str::camel(substr($field, 0, -3));
    }
}
