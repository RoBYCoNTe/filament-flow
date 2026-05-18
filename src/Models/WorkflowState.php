<?php

/** @noinspection PhpUnused */

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static where(string $string, $id)
 * @method static firstOrCreate(array $array, array $array1)
 * @method static create(array|bool[]|int[]|mixed[]|string[] $array_merge)
 *
 * @property string $name
 * @property int $id
 * @property string $label
 * @property string $description
 * @property string $color
 * @property bool $is_initial
 * @property bool $is_final
 */
class WorkflowState extends Model
{
    protected $fillable = [
        'workflow_id',
        'name',
        'label',
        'class_name',
        'color',
        'icon',
        'description',
        'sort_order',
        'is_initial',
        'is_final',
        'metadata',
    ];

    protected $casts = [
        'is_initial' => 'boolean',
        'is_final' => 'boolean',
        'metadata' => 'array',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(WorkflowStateField::class, 'state_id');
    }

    public function visibility(): HasMany
    {
        return $this->hasMany(WorkflowStateVisibility::class, 'state_id');
    }

    public function transitionsFrom(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'from_state_id');
    }

    public function transitionsTo(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'to_state_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(WorkflowNotification::class, 'state_id');
    }

    public function accessRules(): HasMany
    {
        return $this->hasMany(WorkflowStateAccessRule::class, 'state_id');
    }
}
