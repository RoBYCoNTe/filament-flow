<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * User model for testing workflow assignments and access control
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $role
 *
 * @method static create(array|string[] $array_merge)
 * @method static find(int $id)
 * @method static where(string $string, string $string1)
 */
class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
    ];

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Get the user's roles (for DefaultRoleResolver compatibility)
     *
     * @return array<string>
     */
    public function getRoles(): array
    {
        if ($this->role === null) {
            return [];
        }

        // Support comma-separated roles
        return array_map('trim', explode(',', $this->role));
    }
}
