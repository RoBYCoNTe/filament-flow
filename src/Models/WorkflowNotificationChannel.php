<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static create(array $array)
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

    public function notification(): BelongsTo
    {
        return $this->belongsTo(WorkflowNotification::class, 'notification_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WorkflowNotificationTemplate::class, 'channel_id');
    }
}
