<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static create(array $array)
 */
class WorkflowNotificationTemplate extends Model
{
    protected $fillable = [
        'notification_id',
        'channel_id',
        'subject',
        'title',
        'body',
        'action_text',
        'action_url',
        'template_engine',
        'variables',
        'format',
        'metadata',
    ];

    protected $casts = [
        'variables' => 'array',
        'metadata' => 'array',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(WorkflowNotification::class, 'notification_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WorkflowNotificationChannel::class, 'channel_id');
    }
}
