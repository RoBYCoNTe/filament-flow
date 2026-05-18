<?php

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function check(): BelongsTo
    {
        return $this->belongsTo(WorkflowScheduledCheck::class, 'check_id');
    }
}
