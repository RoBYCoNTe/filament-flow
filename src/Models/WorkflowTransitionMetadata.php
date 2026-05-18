<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransitionMetadata extends Model
{
    protected $fillable = [
        'transition_history_id',
        'form_data',
        'field_changes',
        'validation_errors',
        'rules_evaluated',
        'related_changes',
        'custom_data',
    ];

    protected $casts = [
        'form_data' => 'array',
        'field_changes' => 'array',
        'validation_errors' => 'array',
        'rules_evaluated' => 'array',
        'related_changes' => 'array',
        'custom_data' => 'array',
    ];

    public function transition(): BelongsTo
    {
        return $this->belongsTo(WorkflowStateTransition::class, 'transition_history_id');
    }
}
