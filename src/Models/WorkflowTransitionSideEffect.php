<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transition_id
 * @property string $effect_type
 * @property string $field_name
 * @property string|null $value_expression
 * @property int $sort_order
 * @property bool $is_active
 * @property-read WorkflowTransition|null $transition
 */
class WorkflowTransitionSideEffect extends Model
{
    protected $fillable = [
        'transition_id',
        'effect_type',
        'field_name',
        'value_expression',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<WorkflowTransition, $this> */
    public function transition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTransition::class, 'transition_id');
    }
}
