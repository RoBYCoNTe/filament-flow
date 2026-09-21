<?php

/** @noinspection PhpPossiblePolymorphicInvocationInspection */

/** @noinspection PhpUnused */

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoBYCoNTe\FilamentFlow\Concerns\ResolvesUserModel;

/**
 * @method static create(array $array)
 * @method static where(string $string, $id)
 *
 * @property string $from_state
 * @property string $to_state
 * @property string $transitionable_type
 * @property string $transitionable_id
 * @property int|null $workflow_id
 * @property int|null $transition_id
 * @property string|null $from_state_label
 * @property string $to_state_label
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string|null $user_email
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $reason
 * @property string|null $notes
 * @property Carbon $created_at
 * @property int|null $duration_seconds
 * @property bool $has_metadata
 * @property bool $has_snapshot
 * @property bool $is_visible
 * @property-read Workflow|null $workflow
 * @property-read WorkflowTransition|null $transition
 * @property-read WorkflowTransitionMetadata|null $metadata
 * @property-read Collection<int, WorkflowTransitionSnapshot> $snapshots
 * @property-read WorkflowTransitionSnapshot|null $snapshotBefore
 * @property-read WorkflowTransitionSnapshot|null $snapshotAfter
 */
class WorkflowStateTransition extends Model
{
    use ResolvesUserModel;

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

    /** @return HasOne<WorkflowTransitionMetadata, $this> */
    public function metadata(): HasOne
    {
        return $this->hasOne(WorkflowTransitionMetadata::class, 'transition_history_id');
    }

    /** @return HasMany<WorkflowTransitionSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(WorkflowTransitionSnapshot::class, 'transition_history_id');
    }

    /** @return HasOne<WorkflowTransitionSnapshot, $this> */
    public function snapshotBefore(): HasOne
    {
        return $this->hasOne(WorkflowTransitionSnapshot::class, 'transition_history_id')
            ->where('snapshot_type', 'before');
    }

    /** @return HasOne<WorkflowTransitionSnapshot, $this> */
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
            ->where('transitionable_id', $record->getKey());
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
