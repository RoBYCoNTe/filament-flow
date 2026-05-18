<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static create(array $array)
 * @method static where(string $string, int $id)
 */
class WorkflowNotification extends Model
{
    protected $fillable = [
        'workflow_id',
        'transition_id',
        'state_id',
        'trigger_event',
        'name',
        'description',
        'is_active',
        'timing',
        'delay_minutes',
        'priority',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function transition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTransition::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WorkflowNotificationRecipient::class, 'notification_id');
    }

    public function channels(): HasMany
    {
        return $this->hasMany(WorkflowNotificationChannel::class, 'notification_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WorkflowNotificationTemplate::class, 'notification_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WorkflowNotificationLog::class, 'notification_id');
    }
}
