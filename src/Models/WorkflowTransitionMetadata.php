<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The extra values a transition carries, as rows: what a step wrote beside the record — an
 * amount, a reference — keyed by the transition that wrote it.
 */
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

    /** @return BelongsTo<WorkflowStateTransition, $this> */
    public function transition(): BelongsTo
    {
        return $this->belongsTo(WorkflowStateTransition::class, 'transition_history_id');
    }
}
