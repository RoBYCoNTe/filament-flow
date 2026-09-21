<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $notification_id
 * @property int $channel_id
 * @property string|null $subject
 * @property string|null $title
 * @property string $body
 * @property string|null $action_text
 * @property string|null $action_url
 * @property string $template_engine
 * @property string $format
 * @property list<string>|null $variables
 * @property array<string,mixed>|null $metadata
 * @property-read WorkflowNotification|null $notification
 * @property-read WorkflowNotificationChannel|null $channel
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

    /** @return BelongsTo<WorkflowNotification, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(WorkflowNotification::class, 'notification_id');
    }

    /** @return BelongsTo<WorkflowNotificationChannel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(WorkflowNotificationChannel::class, 'channel_id');
    }
}
