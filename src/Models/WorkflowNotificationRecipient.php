<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static create(array $array)
 *
 * @property int $id
 * @property int $notification_id
 * @property string $recipient_type
 * @property array $recipient_config
 * @property int $sort_order
 */
class WorkflowNotificationRecipient extends Model
{
    protected $fillable = [
        'notification_id',
        'recipient_type',
        'recipient_config',
        'sort_order',
    ];

    protected $casts = [
        'recipient_config' => 'array',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(WorkflowNotification::class, 'notification_id');
    }
}
