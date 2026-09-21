<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use RoBYCoNTe\FilamentFlow\Concerns\ResolvesUserModel;

/**
 * @method static where(string $string, $id)
 * @method static create(array $array)
 *
 * @property int $notification_id
 * @property int|null $user_id
 * @property string $notifiable_type
 * @property string $notifiable_id
 * @property string $channel
 * @property string $status
 * @property string|null $error_message
 * @property array<string,mixed>|null $payload
 * @property Carbon|null $sent_at
 * @property-read WorkflowNotification|null $notification
 */
class WorkflowNotificationLog extends Model
{
    use ResolvesUserModel;

    protected $fillable = [
        'notification_id',
        'user_id',
        'notifiable_type',
        'notifiable_id',
        'channel',
        'status',
        'error_message',
        'payload',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
    ];

    /** @return BelongsTo<WorkflowNotification, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(WorkflowNotification::class, 'notification_id');
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
