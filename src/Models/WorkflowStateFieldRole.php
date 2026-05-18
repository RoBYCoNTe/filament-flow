<?php

/** @noinspection PhpUnused */

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function stateField(): BelongsTo
    {
        return $this->belongsTo(WorkflowStateField::class, 'state_field_id');
    }
}
