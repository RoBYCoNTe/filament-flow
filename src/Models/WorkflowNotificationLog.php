<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @method static where(string $string, $id)
 * @method static create(array $array)
 */
class WorkflowNotificationLog extends Model
{
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

    public function notification(): BelongsTo
    {
        return $this->belongsTo(WorkflowNotification::class, 'notification_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo($this->getUserModel());
    }

    protected function getUserModel(): string
    {
        return config('filament-flow.user_model') ?? config('auth.providers.users.model', User::class);
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
