<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransitionValidationRule extends Model
{
    protected $fillable = [
        'transition_id',
        'field_name',
        'rules',
        'custom_message',
        'sort_order',
    ];

    protected $casts = [
        'rules' => 'array',
    ];

    public function transition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTransition::class, 'transition_id');
    }
}
