<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $notification_id
 * @property string $channel_type
 * @property array<string,mixed>|null $channel_config
 * @property bool $is_active
 * @property-read WorkflowNotification|null $notification
 * @property-read Collection<int, WorkflowNotificationTemplate> $templates
 */
class WorkflowNotificationChannel extends Model
{
    protected $fillable = [
        'notification_id',
        'channel_type',
        'channel_config',
        'is_active',
    ];

    protected $casts = [
        'channel_config' => 'array',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<WorkflowNotification, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(WorkflowNotification::class, 'notification_id');
    }

    /** @return HasMany<WorkflowNotificationTemplate, $this> */
    public function templates(): HasMany
    {
        return $this->hasMany(WorkflowNotificationTemplate::class, 'channel_id');
    }
}
