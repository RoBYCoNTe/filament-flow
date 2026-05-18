<?php

/** @noinspection PhpUnused */

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static create(array $array)
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

    public function state(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'state_id');
    }

    public function roleOverrides(): HasMany
    {
        return $this->hasMany(WorkflowStateFieldRole::class, 'state_field_id');
    }
}
