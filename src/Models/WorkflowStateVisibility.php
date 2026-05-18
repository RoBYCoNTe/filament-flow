<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStateVisibility extends Model
{
    protected $fillable = [
        'state_id',
        'visibility_type',
        'visibility_config',
        'allow_admin_override',
    ];

    protected $casts = [
        'visibility_config' => 'array',
        'allow_admin_override' => 'boolean',
    ];

    public function state(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'state_id');
    }
}
