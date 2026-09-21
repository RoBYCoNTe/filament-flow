<?php

/** @noinspection PhpUnused */

/** @noinspection PhpComposerExtensionStubsInspection */

namespace RoBYCoNTe\FilamentFlow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $transition_history_id
 * @property string $snapshot_type
 * @property array<string,mixed> $record_data
 * @property array<string,mixed>|null $related_data
 * @property bool $is_compressed
 * @property Carbon $created_at
 * @property-read WorkflowStateTransition|null $transition
 */
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

    /** @return BelongsTo<WorkflowStateTransition, $this> */
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
            // Compressed payloads are base64-encoded: unwrap the JSON string
            // wrapper so the json/jsonb column stays valid (legacy raw base64
            // values are handled too).
            $payload = json_decode($value, true);

            if (! is_string($payload)) {
                $payload = $value;
            }

            return json_decode(gzuncompress(base64_decode($payload)), true);
        }

        return json_decode($value, true);
    }

    /**
     * Set and optionally compress record data
     */
    public function setRecordDataAttribute($value): void
    {
        $json = json_encode($value);

        // Compress if larger than 1KB. The compressed payload is base64-encoded
        // and wrapped in JSON so the json/jsonb column accepts it.
        if (strlen($json) > 1024) {
            $this->attributes['record_data'] = json_encode(base64_encode(gzcompress($json)));
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
            // Compressed payloads are base64-encoded: unwrap the JSON string
            // wrapper so the json/jsonb column stays valid (legacy raw base64
            // values are handled too).
            $payload = json_decode($value, true);

            if (! is_string($payload)) {
                $payload = $value;
            }

            return json_decode(gzuncompress(base64_decode($payload)), true);
        }

        return json_decode($value, true);
    }
}
