<?php

namespace RoBYCoNTe\FilamentFlow\Support;

/**
 * Lays a request scope over the permissions a state already gave.
 *
 * It works on the result of the state rules, never inside them: those are cached for every
 * record in the same state, and what one request opens must not reach the others.
 */
final class RequestScopeOverlay
{
    /**
     * The flat map of a state (`getFieldPermissions`), with the scope laid over it.
     *
     * A rule on an ancestor is not enough here: readers take the most specific rule, so a
     * configured column under a scoped section would stay locked. Every path of the universe
     * is therefore written out.
     *
     * @param  array<string,array<string,mixed>>  $map
     * @param  array{mode:string,paths:list<string>,whitelist:list<string>|null,universe:list<string>}  $scope
     * @return array<string,array<string,mixed>>
     */
    public static function applyToMap(array $map, array $scope): array
    {
        $candidates = array_unique([...array_keys($map), ...$scope['universe']]);

        foreach ($candidates as $path) {
            $resolved = self::applyToResolved(self::inherited($map, $path), $path, $scope);

            if ($resolved !== null) {
                $map[$path] = $resolved;
            }
        }

        return $map;
    }

    /**
     * The permission of one path, with the scope laid over it.
     *
     * @param  array<string,mixed>|null  $resolved  what the state says; null is "unconfigured"
     * @param  array{mode:string,paths:list<string>,whitelist:list<string>|null,universe:list<string>}  $scope
     * @return array<string,mixed>|null
     */
    public static function applyToResolved(?array $resolved, string $path, array $scope): ?array
    {
        if (self::within($path, $scope['paths'])) {
            // What the whitelist does not name is never opened, even if the scope names it.
            if ($scope['whitelist'] !== null && ! self::within($path, $scope['whitelist'])) {
                return $resolved;
            }

            return array_replace(
                $resolved ?? ['required' => false, 'validation' => null],
                ['visible' => true, 'readonly' => false, 'locked' => false],
            );
        }

        // A container of something in scope stays as the state left it.
        if (self::contains($path, $scope['paths'])) {
            return $resolved;
        }

        if ($scope['mode'] === 'exclusive') {
            return array_replace(
                $resolved ?? ['visible' => true, 'locked' => false, 'required' => false, 'validation' => null],
                ['readonly' => true],
            );
        }

        return $resolved;
    }

    /**
     * @param  array<string,array<string,mixed>>  $map
     * @return array<string,mixed>|null
     */
    private static function inherited(array $map, string $path): ?array
    {
        $segments = explode('.', $path);

        while ($segments !== []) {
            $candidate = implode('.', $segments);

            if (isset($map[$candidate])) {
                return $map[$candidate];
            }

            array_pop($segments);
        }

        return null;
    }

    /**
     * @param  list<string>  $roots
     */
    private static function within(string $path, array $roots): bool
    {
        foreach ($roots as $root) {
            if ($path === $root || str_starts_with($path, $root.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $roots
     */
    private static function contains(string $path, array $roots): bool
    {
        foreach ($roots as $root) {
            if (str_starts_with($root, $path.'.')) {
                return true;
            }
        }

        return false;
    }
}
