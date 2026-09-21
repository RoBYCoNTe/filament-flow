<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $workflow_id
 * @property int|null $transition_id
 * @property int|null $state_id
 * @property string $trigger_event
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property string $timing
 * @property int|null $delay_minutes
 * @property string $priority
 * @property array<string,mixed>|null $metadata
 *
 * @method static create(array $array)
 * @method static where(string $string, int $id)
 *
 * @property-read Workflow|null $workflow
 * @property-read WorkflowTransition|null $transition
 * @property-read WorkflowState|null $state
 * @property-read Collection<int, WorkflowNotificationRecipient> $recipients
 * @property-read Collection<int, WorkflowNotificationChannel> $channels
 * @property-read Collection<int, WorkflowNotificationTemplate> $templates
 * @property-read Collection<int, WorkflowNotificationLog> $logs
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

    /** @return BelongsTo<Workflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /** @return BelongsTo<WorkflowTransition, $this> */
    public function transition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTransition::class);
    }

    /** @return BelongsTo<WorkflowState, $this> */
    public function state(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class);
    }

    /** @return HasMany<WorkflowNotificationRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(WorkflowNotificationRecipient::class, 'notification_id');
    }

    /** @return HasMany<WorkflowNotificationChannel, $this> */
    public function channels(): HasMany
    {
        return $this->hasMany(WorkflowNotificationChannel::class, 'notification_id');
    }

    /** @return HasMany<WorkflowNotificationTemplate, $this> */
    public function templates(): HasMany
    {
        return $this->hasMany(WorkflowNotificationTemplate::class, 'notification_id');
    }

    /** @return HasMany<WorkflowNotificationLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(WorkflowNotificationLog::class, 'notification_id');
    }
}
