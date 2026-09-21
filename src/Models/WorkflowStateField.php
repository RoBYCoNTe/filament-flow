<?php

/** @noinspection PhpUnused */

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $state_id
 * @property string $field_name
 * @property string $visibility
 * @property string $mutability
 * @property bool $is_required
 * @property int $sort_order
 * @property array<int,string>|null $validation_rules
 *
 * @method static create(array $array)
 *
 * @property-read WorkflowState|null $state
 * @property-read Collection<int, WorkflowStateFieldRole> $roleOverrides
 */
class WorkflowStateField extends Model
{
    protected $fillable = [
        'state_id',
        'field_name',
        'visibility',
        'mutability',
        'is_required',
        'sort_order',
        'validation_rules',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'validation_rules' => 'array',
    ];

    /** @return BelongsTo<WorkflowState, $this> */
    public function state(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'state_id');
    }

    /** @return HasMany<WorkflowStateFieldRole, $this> */
    public function roleOverrides(): HasMany
    {
        return $this->hasMany(WorkflowStateFieldRole::class, 'state_field_id');
    }
}
