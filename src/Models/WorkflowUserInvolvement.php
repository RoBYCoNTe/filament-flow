<?php

/** @noinspection PhpPossiblePolymorphicInvocationInspection */

/** @noinspection PhpUnused */

namespace RoBYCoNTe\FilamentFlow\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static create(array $array)
 */
class WorkflowUserInvolvement extends Model
{
    protected $table = 'workflow_user_involvement';

    protected $fillable = [
        'model_type',
        'model_id',
        'user_id',
        'involvement_type',
        'state',
        'first_involved_at',
        'last_involved_at',
        'involvement_count',
    ];

    protected $casts = [
        'first_involved_at' => 'datetime',
        'last_involved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo($this->getUserModel());
    }

    protected function getUserModel(): string
    {
        return config('filament-flow.user_model') ?? config('auth.providers.users.model', User::class);
    }

    /**
     * Scope: Get involved users for a record
     */
    public function scopeForRecord($query, Model $record)
    {
        return $query->where('model_type', get_class($record))
            ->where('model_id', $record->id);
    }

    /**
     * Scope: Filter by involvement type
     */
    public function scopeOfType($query, string|array $types)
    {
        $types = is_array($types) ? $types : [$types];

        return $query->whereIn('involvement_type', $types);
    }

    /**
     * Scope: Filter by state
     */
    public function scopeInState($query, string|array $states)
    {
        $states = is_array($states) ? $states : [$states];

        return $query->whereIn('state', $states);
    }
}
