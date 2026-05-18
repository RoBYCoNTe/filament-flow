<?php

/** @noinspection PhpUnused */

/** @noinspection PhpComposerExtensionStubsInspection */

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransitionSnapshot extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'transition_history_id',
        'snapshot_type',
        'record_data',
        'related_data',
        'is_compressed',
    ];

    protected $casts = [
        'is_compressed' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function transition(): BelongsTo
    {
        return $this->belongsTo(WorkflowStateTransition::class, 'transition_history_id');
    }

    /**
     * Get decompressed record data
     */
    public function getRecordDataAttribute($value)
    {
        if ($this->is_compressed && $value) {
            return json_decode(gzuncompress(base64_decode($value)), true);
        }

        return json_decode($value, true);
    }

    /**
     * Set and optionally compress record data
     */
    public function setRecordDataAttribute($value): void
    {
        $json = json_encode($value);

        // Compress if larger than 1KB
        if (strlen($json) > 1024) {
            $this->attributes['record_data'] = base64_encode(gzcompress($json));
            $this->attributes['is_compressed'] = true;
        } else {
            $this->attributes['record_data'] = $json;
            $this->attributes['is_compressed'] = false;
        }
    }

    /**
     * Get related data with decompression if needed
     */
    public function getRelatedDataAttribute($value)
    {
        if ($this->is_compressed && $value) {
            return json_decode(gzuncompress(base64_decode($value)), true);
        }

        return json_decode($value, true);
    }
}
