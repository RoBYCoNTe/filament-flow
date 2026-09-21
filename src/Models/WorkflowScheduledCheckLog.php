<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $check_id
 * @property string $model_type
 * @property string $model_id
 * @property string $result
 * @property array<string,mixed>|null $metadata
 * @property Carbon $executed_at
 * @property-read WorkflowScheduledCheck|null $check
 */
class WorkflowScheduledCheckLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'check_id',
        'model_type',
        'model_id',
        'result',
        'metadata',
        'executed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'executed_at' => 'datetime',
    ];

    /** @return BelongsTo<WorkflowScheduledCheck, $this> */
    public function check(): BelongsTo
    {
        return $this->belongsTo(WorkflowScheduledCheck::class, 'check_id');
    }
}
