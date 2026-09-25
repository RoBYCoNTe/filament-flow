<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Support\Str;

/**
 * The words a role reads in.
 *
 * A role name (`super_admin`) is a key, not a sentence: the host knows what its own roles are
 * called, and says so — through a map of names to labels, or through the translations it keeps
 * for its vocabulary. What remains is the name, read as it is written.
 */
final class RoleLabel
{
    /**
     * @param  array<string, string>  $labels  the words of the host, keyed by role name
     */
    public static function for(string $role, array $labels = []): string
    {
        $configured = $labels[$role] ?? null;

        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        $headline = Str::headline($role);

        // The words of the package, then the name as it is translated, then the same headlined:
        // a host that writes its role labels down has them read here as well.
        foreach (['filament-flow::roles.'.$role, $role, $headline] as $key) {
            $translated = __($key);

            if (is_string($translated) && $translated !== $key) {
                return $translated;
            }
        }

        return $headline;
    }
}
