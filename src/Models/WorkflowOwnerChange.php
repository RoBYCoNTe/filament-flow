<?php

/** @noinspection PhpUnused */

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use RoBYCoNTe\FilamentFlow\Concerns\ResolvesUserModel;

/**
 * A handover of a record: who held it, who holds it now, what the previous holder kept and
 * who made the change — the history the owner column reads.
 *
 * @property int $id
 * @property string $changeable_type
 * @property int|string $changeable_id
 * @property int|null $from_user_id
 * @property int|null $to_user_id
 * @property int|null $changed_by
 * @property string|null $owner_field
 * @property string $retention
 * @property string|null $note
 * @property array<string,mixed>|null $metadata
 * @property Carbon|null $changed_at
 */
class WorkflowOwnerChange extends Model
{
    use ResolvesUserModel;

    protected $table = 'workflow_owner_changes';

    protected $fillable = [
        'changeable_type',
        'changeable_id',
        'from_user_id',
        'to_user_id',
        'changed_by',
        'owner_field',
        'retention',
        'note',
        'metadata',
        'changed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'changed_at' => 'datetime',
    ];

    public function changeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo($this->getUserModel(), 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo($this->getUserModel(), 'to_user_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo($this->getUserModel(), 'changed_by');
    }

    /** The handovers of a record, most recent first. */
    public function scopeForRecord($query, Model $record): mixed
    {
        return $query
            ->where('changeable_type', $record->getMorphClass())
            ->where('changeable_id', $record->getKey());
    }

    /** Whether the previous holder kept a role on the record. */
    public function previousHolderStayed(): bool
    {
        return $this->retention !== 'none';
    }
}
