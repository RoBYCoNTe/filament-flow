<?php

/** @noinspection PhpPossiblePolymorphicInvocationInspection */

/** @noinspection PhpUnused */

namespace RoBYCoNTe\FilamentFlow\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @method static create(array $array)
 * @method static where(string $string, $id)
 *
 * @property string $from_state
 * @property string $to_state
 */
class WorkflowStateTransition extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'transitionable_type',
        'transitionable_id',
        'workflow_id',
        'transition_id',
        'from_state',
        'to_state',
        'from_state_label',
        'to_state_label',
        'user_id',
        'user_name',
        'user_email',
        'ip_address',
        'user_agent',
        'reason',
        'notes',
        'duration_seconds',
        'has_metadata',
        'has_snapshot',
        'is_visible',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'has_metadata' => 'boolean',
        'has_snapshot' => 'boolean',
        'is_visible' => 'boolean',
    ];

    public function transitionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function transition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTransition::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo($this->getUserModel());
    }

    protected function getUserModel(): string
    {
        return config('filament-flow.user_model') ?? config('auth.providers.users.model', User::class);
    }

    public function metadata(): HasOne
    {
        return $this->hasOne(WorkflowTransitionMetadata::class, 'transition_history_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(WorkflowTransitionSnapshot::class, 'transition_history_id');
    }

    public function snapshotBefore(): HasOne
    {
        return $this->hasOne(WorkflowTransitionSnapshot::class, 'transition_history_id')
            ->where('snapshot_type', 'before');
    }

    public function snapshotAfter(): HasOne
    {
        return $this->hasOne(WorkflowTransitionSnapshot::class, 'transition_history_id')
            ->where('snapshot_type', 'after');
    }

    /**
     * Scope: Get transitions for a specific record
     */
    public function scopeForRecord($query, Model $record)
    {
        return $query->where('transitionable_type', get_class($record))
            ->where('transitionable_id', $record->id);
    }

    /**
     * Scope: Get transitions by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Get transitions in date range
     */
    public function scopeBetweenDates($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Scope: Get transitions to specific state
     */
    public function scopeToState($query, string $stateClass)
    {
        return $query->where('to_state', $stateClass);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    /**
     * Is this an in-state action (state didn't change)?
     */
    public function isAction(): bool
    {
        return $this->from_state === $this->to_state
            || $this->to_state === null;
    }
}
