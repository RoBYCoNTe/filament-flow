<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A visibility rule of a state as it is stored: the rule, and the state it belongs to. The rows
 * carry the states of a workflow as much as the states themselves do.
 */
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

    /** @return BelongsTo<WorkflowState, $this> */
    public function state(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'state_id');
    }
}
