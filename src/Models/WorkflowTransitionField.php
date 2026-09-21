<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $transition_id
 * @property string $field_name
 * @property string $field_type
 * @property string $label
 * @property string|null $model_attribute
 * @property string $mapping_type
 * @property array<string,mixed>|null $mapping_config
 * @property bool $is_required
 * @property array<string,mixed>|null $validation_rules
 * @property string|null $custom_validation_class
 * @property int $sort_order
 * @property array<string,mixed>|null $field_config
 * @property bool $save_to_model
 * @property-read WorkflowTransition|null $transition
 */
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

    /** @return BelongsTo<WorkflowTransition, $this> */
    public function transition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTransition::class, 'transition_id');
    }
}
