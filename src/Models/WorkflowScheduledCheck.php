<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowScheduledCheck extends Model
{
    protected $fillable = [
        'workflow_id',
        'name',
        'description',
        'state_id',
        'condition_type',
        'condition_config',
        'action_type',
        'action_config',
        'frequency',
        'once_per_record',
        'is_active',
        'last_checked_at',
    ];

    protected $casts = [
        'condition_config' => 'array',
        'action_config' => 'array',
        'once_per_record' => 'boolean',
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'state_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WorkflowScheduledCheckLog::class, 'check_id');
    }

    public function isDue(): bool
    {
        if (! $this->last_checked_at) {
            return true;
        }

        return match ($this->frequency) {
            'every_minute' => $this->last_checked_at->addMinute()->isPast(),
            'every_five_minutes' => $this->last_checked_at->addMinutes(5)->isPast(),
            'hourly' => $this->last_checked_at->addHour()->isPast(),
            'daily' => $this->last_checked_at->addDay()->isPast(),
            'weekly' => $this->last_checked_at->addWeek()->isPast(),
            default => false,
        };
    }

    public function hasAlreadyExecutedFor(string $modelType, int $modelId): bool
    {
        if (! $this->once_per_record) {
            return false;
        }

        return $this->logs()
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->where('result', 'triggered')
            ->exists();
    }
}
