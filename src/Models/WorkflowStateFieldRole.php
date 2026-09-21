<?php

/** @noinspection PhpUnused */

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $state_field_id
 * @property string $role_name
 * @property string|null $visibility
 * @property string|null $mutability
 * @property bool|null $is_required
 *
 * @method static create(array $array)
 *
 * @property-read WorkflowStateField|null $stateField
 */
class WorkflowStateFieldRole extends Model
{
    protected $fillable = [
        'state_field_id',
        'role_name',
        'visibility',
        'mutability',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    /** @return BelongsTo<WorkflowStateField, $this> */
    public function stateField(): BelongsTo
    {
        return $this->belongsTo(WorkflowStateField::class, 'state_field_id');
    }
}
