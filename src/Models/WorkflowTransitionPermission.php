<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property mixed $transition_id
 * @property string $permission_type
 * @property string|null $permission_value
 * @property bool $require_all
 * @property array<string,mixed>|null $metadata
 * @property-read WorkflowTransition|null $transition
 */
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

    /** @return BelongsTo<WorkflowTransition, $this> */
    public function transition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTransition::class, 'transition_id');
    }
}
