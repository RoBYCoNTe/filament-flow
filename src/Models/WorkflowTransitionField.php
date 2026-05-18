<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransitionField extends Model
{
    protected $fillable = [
        'transition_id',
        'field_name',
        'field_type',
        'label',
        'model_attribute',
        'mapping_type',
        'mapping_config',
        'is_required',
        'validation_rules',
        'custom_validation_class',
        'sort_order',
        'field_config',
        'save_to_model',
    ];

    protected $casts = [
        'mapping_config' => 'array',
        'is_required' => 'boolean',
        'validation_rules' => 'array',
        'field_config' => 'array',
        'save_to_model' => 'boolean',
    ];

    public function transition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTransition::class, 'transition_id');
    }
}
