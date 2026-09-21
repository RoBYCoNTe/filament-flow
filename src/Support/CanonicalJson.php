<?php

namespace RoBYCoNTe\FilamentFlow\Support;

/**
 * Canonical JSON comparison helpers used by the planners.
 *
 * PostgreSQL reorders the keys of a `jsonb` column, so two runs of the same
 * definition would look different. Sorting the keys recursively before encoding
 * makes the comparison stable.
 */
final class CanonicalJson
{
    /**
     * Canonical JSON of a value: array keys sorted recursively.
     */
    public static function encode(mixed $value): string
    {
        return json_encode(self::canonicalize($value), JSON_THROW_ON_ERROR);
    }

    /**
     * Canonical JSON of an optional value: null and an empty array are
     * equivalent, so an absent JSON column and `[]` do not look like a change.
     */
    public static function encodeOptional(mixed $value): string
    {
        if ($value === null || $value === []) {
            return '';
        }

        if (is_array($value)) {
            return self::encode($value);
        }

        return (string) $value;
    }

    /**
     * Sorts the keys of every associative array, recursively. List order is
     * significant and is preserved.
     */
    public static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        ksort($value);

        return array_map(self::canonicalize(...), $value);
    }
}
