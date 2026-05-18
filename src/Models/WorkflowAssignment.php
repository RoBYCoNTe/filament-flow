<?php

/** @noinspection PhpUnused */

namespace RoBYCoNTe\FilamentFlow\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkflowAssignment extends Model
{
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

    public function user(): BelongsTo
    {
        return $this->belongsTo($this->getUserModel());
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo($this->getUserModel(), 'assigned_by');
    }

    protected function getUserModel(): string
    {
        return config('filament-flow.user_model') ?? config('auth.providers.users.model', User::class);
    }
}
