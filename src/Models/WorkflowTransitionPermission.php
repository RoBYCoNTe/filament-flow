<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransitionPermission extends Model
{
    protected $fillable = [
        'transition_id',
        'permission_type',
        'permission_value',
        'require_all',
        'metadata',
    ];

    protected $casts = [
        'require_all' => 'boolean',
        'metadata' => 'array',
    ];

    public function transition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTransition::class, 'transition_id');
    }
}
