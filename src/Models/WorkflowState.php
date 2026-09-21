<?php

/** @noinspection PhpUnused */

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @method static where(string $string, $id)
 * @method static firstOrCreate(array $array, array $array1)
 * @method static create(array|bool[]|int[]|mixed[]|string[] $array_merge)
 *
 * @property string $name
 * @property int $id
 * @property int $workflow_id
 * @property string $label
 * @property string|null $class_name
 * @property string $description
 * @property string $color
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_initial
 * @property bool $is_final
 * @property array<string,mixed>|null $metadata
 * @property-read Workflow|null $workflow
 * @property-read Collection<int, WorkflowStateField> $fields
 * @property-read Collection<int, WorkflowStateVisibility> $visibility
 * @property-read Collection<int, WorkflowTransition> $transitionsFrom
 * @property-read Collection<int, WorkflowTransition> $transitionsTo
 * @property-read Collection<int, WorkflowNotification> $notifications
 * @property-read Collection<int, WorkflowStateAccessRule> $accessRules
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

    /** @return BelongsTo<Workflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /** @return HasMany<WorkflowStateField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(WorkflowStateField::class, 'state_id');
    }

    /** @return HasMany<WorkflowStateVisibility, $this> */
    public function visibility(): HasMany
    {
        return $this->hasMany(WorkflowStateVisibility::class, 'state_id');
    }

    /** @return HasMany<WorkflowTransition, $this> */
    public function transitionsFrom(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'from_state_id');
    }

    /** @return HasMany<WorkflowTransition, $this> */
    public function transitionsTo(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'to_state_id');
    }

    /** @return HasMany<WorkflowNotification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(WorkflowNotification::class, 'state_id');
    }

    /** @return HasMany<WorkflowStateAccessRule, $this> */
    public function accessRules(): HasMany
    {
        return $this->hasMany(WorkflowStateAccessRule::class, 'state_id');
    }
}
