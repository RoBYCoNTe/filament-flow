<?php

/** @noinspection PhpUnused */

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use RoBYCoNTe\FilamentFlow\Concerns\ResolvesUserModel;

/**
 * @property int $id
 * @property string $assignable_type
 * @property int|string $assignable_id
 * @property int $user_id
 * @property string $assignment_type
 * @property Carbon|null $assigned_at
 * @property int|null $assigned_by
 * @property array<string,mixed>|null $metadata
 * @property bool|null $override_view
 * @property bool|null $override_edit
 * @property bool|null $override_transition
 */
class WorkflowAssignment extends Model
{
    use ResolvesUserModel;

    protected $fillable = [
        'assignable_type',
        'assignable_id',
        'user_id',
        'assignment_type',
        'assigned_at',
        'assigned_by',
        'metadata',
        'override_view',
        'override_edit',
        'override_transition',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'metadata' => 'array',
        'override_view' => 'boolean',
        'override_edit' => 'boolean',
        'override_transition' => 'boolean',
    ];

    public function getMetadata(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->metadata;
        }

        return $this->metadata[$key] ?? null;
    }

    /**
     * Check if this assignment has any access override.
     */
    public function hasAccessOverride(): bool
    {
        return $this->override_view === true
            || $this->override_edit === true
            || $this->override_transition === true;
    }

    /**
     * Check if this assignment has a specific access override.
     */
    public function hasOverrideFor(string $accessType): bool
    {
        return match ($accessType) {
            'view' => $this->override_view === true,
            'edit' => $this->override_edit === true,
            'transition' => $this->override_transition === true,
            default => false,
        };
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo($this->getUserModel(), 'assigned_by');
    }
}
