<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * A person as the panels and the columns show them: the name, the initials of the avatar, and
 * the words the host uses for the roles they carry.
 *
 * The shape was built by hand in five places — the panel of the assignments, the owner column,
 * the column of the assignments, the summary of an assignment — and every copy drifted a little.
 * Here it is written once.
 */
final class UserSummary
{
    /**
     * @param  array<string, string>  $roleLabels  the words of the host, keyed by role name
     * @return array{id:int|string, name:string, initials:string, roles:string}
     */
    public static function of(Model $user, array $roleLabels = []): array
    {
        $name = (string) $user->getAttribute('name');

        return [
            'id' => $user->getKey(),
            'name' => $name,
            'initials' => self::initials($name),
            'roles' => self::roles($user, $roleLabels),
        ];
    }

    /** The two letters of an avatar: the initials of a name, or its first two letters. */
    public static function initials(string $name): string
    {
        $parts = explode(' ', trim($name));

        return count($parts) >= 2
            ? mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr(end($parts), 0, 1))
            : mb_strtoupper(mb_substr($name, 0, 2));
    }

    /**
     * The roles of a person, in the words of the host: a role name is a key, and only the host
     * knows how to read it.
     *
     * @param  array<string, string>  $roleLabels
     */
    public static function roles(Model $user, array $roleLabels = []): string
    {
        if (! method_exists($user, 'getRoleNames')) {
            return '';
        }

        return $user->getRoleNames()
            ->map(fn ($role): string => RoleLabel::for((string) $role, $roleLabels))
            ->implode(', ');
    }
}
