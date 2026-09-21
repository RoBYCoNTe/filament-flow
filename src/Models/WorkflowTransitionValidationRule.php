<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transition_id
 * @property string $field_name
 * @property array<int,string> $rules
 * @property string|null $custom_message
 * @property string $rule_type
 * @property string|null $condition
 * @property string|null $label
 * @property int $sort_order
 * @property-read WorkflowTransition|null $transition
 */
class WorkflowTransitionValidationRule extends Model
{
    protected $fillable = [
        'transition_id',
        'field_name',
        'rules',
        'custom_message',
        'rule_type',
        'condition',
        'label',
        'sort_order',
    ];

    protected $casts = [
        'rules' => 'array',
    ];

    /** @return BelongsTo<WorkflowTransition, $this> */
    public function transition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTransition::class, 'transition_id');
    }
}
