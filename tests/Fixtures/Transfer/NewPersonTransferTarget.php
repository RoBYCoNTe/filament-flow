<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\Transfer;

use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RoBYCoNTe\FilamentFlow\Contracts\TransferTargetResolver;
use RoBYCoNTe\FilamentFlow\Support\TransferTarget;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;

/**
 * A resolver of the host: the person is asked for by name and address, found by the address or
 * created on the spot. It refuses an address of the `blocked.test` domain, and writes beside the
 * handover that the person was provisioned.
 */
final class NewPersonTransferTarget implements TransferTargetResolver
{
    public function label(): string
    {
        return 'New person';
    }

    public function formSchema(): array
    {
        return [
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required(),
        ];
    }

    public function resolve(array $payload, ?Authenticatable $actor, Model $record): TransferTarget
    {
        if (str_ends_with((string) $payload['email'], '@blocked.test')) {
            throw ValidationException::withMessages([
                'transferFormData.newPerson.email' => ['This address is not accepted.'],
            ]);
        }

        $user = User::query()->firstOrCreate(
            ['email' => $payload['email']],
            ['name' => $payload['name'], 'password' => bcrypt(Str::random(32))],
        );

        return new TransferTarget($user->getKey(), [
            'provisioned' => $user->wasRecentlyCreated,
            'provisioned_by' => $actor?->getKey(),
        ]);
    }
}
