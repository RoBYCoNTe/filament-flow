<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Support\TransferTarget;

/**
 * Who a record can be handed to when that person is not in the list yet: the host says how they
 * are asked for, and how they are found or created.
 *
 * The package knows nothing of what a person is made of — a tax code, an address, an identity
 * provider. The resolver declares the fields of the form and answers with the id of the user; the
 * handover then runs as any other. Name it in `state_access.transfer_target_resolver`: without
 * it the panel only hands records to the people it already lists.
 *
 * ```php
 * class FiscalCodeTransferTarget implements TransferTargetResolver
 * {
 *     public function label(): string
 *     {
 *         return 'New referent from tax code';
 *     }
 *
 *     public function formSchema(): array
 *     {
 *         return [TextInput::make('fiscal_code')->required()];
 *     }
 *
 *     public function resolve(array $payload, ?Authenticatable $actor, Model $record): TransferTarget
 *     {
 *         return new TransferTarget(Provision::user($payload['fiscal_code'])->id);
 *     }
 * }
 * ```
 */
interface TransferTargetResolver
{
    /** How the choice of a person that is not listed reads in the form. */
    public function label(): string;

    /**
     * The fields that describe the person, shown when that choice is made. Their names are the
     * keys of the payload `resolve()` receives.
     *
     * @return array<int, Component>
     */
    public function formSchema(): array;

    /**
     * Find or create the person, and say who they are. The call runs inside the transaction of
     * the handover: whatever is thrown rolls both back. To refuse a value, throw a
     * `ValidationException` keyed `transferFormData.newPerson.<field>` — the form shows it
     * beside the field.
     *
     * @param  array<string, mixed>  $payload  The values of the fields of `formSchema()`
     * @param  Authenticatable|null  $actor  Who makes the handover
     * @param  Model  $record  The record being handed over
     */
    public function resolve(array $payload, ?Authenticatable $actor, Model $record): TransferTarget;
}
