<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Support\Str;

/**
 * What moved between two sets of values: the paths whose value changed, each with the value it
 * had and the value it took.
 *
 * The comparison runs on paths, not on the shape of the data: a map is opened down to its
 * leaves (`intervention.location.province`), while a list — the rows of a repeater, the files
 * of a document set — stays whole, because a row is the unit a person changed.
 */
final class FieldChanges
{
    /**
     * The paths whose value differs between `$before` and `$after`.
     *
     * @param  array<array-key,mixed>  $before
     * @param  array<array-key,mixed>  $after
     * @param  list<string>  $ignore  paths to leave out, `*` wildcards allowed
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function between(array $before, array $after, array $ignore = []): array
    {
        $flatBefore = self::flatten($before);
        $flatAfter = self::flatten($after);

        $changes = [];

        foreach (array_unique(array_merge(array_keys($flatBefore), array_keys($flatAfter))) as $path) {
            if (self::isIgnored($path, $ignore)) {
                continue;
            }

            $from = $flatBefore[$path] ?? null;
            $to = $flatAfter[$path] ?? null;

            if (self::equals($from, $to)) {
                continue;
            }

            $changes[$path] = ['from' => $from, 'to' => $to];
        }

        return $changes;
    }

    /**
     * The paths of a payload whose value differs from the record as it stood before: what a
     * transition was given, read against the snapshot that precedes it.
     *
     * A host may write the values **after** the transition (a refused transition must not leave
     * the values of an attempt behind), and the payload is then the only place the delta exists.
     * Only the paths the payload names are examined: what it does not mention did not move.
     *
     * @param  array<array-key,mixed>  $before  the values before the transition, as a map of
     *                                          paths (or of the shape `data_get` reads)
     * @param  array<array-key,mixed>  $payload  what the transition was given
     * @param  list<string>  $ignore  paths to leave out, `*` wildcards allowed
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function applied(array $before, array $payload, array $ignore = []): array
    {
        $changes = [];

        foreach (self::flatten($payload) as $path => $to) {
            if (self::isIgnored($path, $ignore)) {
                continue;
            }

            $from = data_get($before, $path);

            if (self::equals($from, $to)) {
                continue;
            }

            $changes[$path] = ['from' => $from, 'to' => $to];
        }

        return $changes;
    }

    /**
     * Every leaf of the values, keyed by path: maps are opened, lists are leaves.
     *
     * @param  array<array-key,mixed>  $values
     * @return array<string, mixed>
     */
    public static function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value) && ! array_is_list($value)) {
                $flat += self::flatten($value, $path);

                continue;
            }

            $flat[$path] = $value;
        }

        return $flat;
    }

    /**
     * Two values are the same when a person would say so: a number written `1` and `1.0`, a
     * missing value and an empty one, a sentence and the same sentence.
     */
    private static function equals(mixed $a, mixed $b): bool
    {
        if (self::isEmpty($a) && self::isEmpty($b)) {
            return true;
        }

        if (is_array($a) || is_array($b)) {
            return $a == $b;
        }

        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return (string) $a === (string) $b;
    }

    private static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /** @param list<string> $ignore */
    private static function isIgnored(string $path, array $ignore): bool
    {
        foreach ($ignore as $pattern) {
            if ($pattern === $path || Str::is($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
