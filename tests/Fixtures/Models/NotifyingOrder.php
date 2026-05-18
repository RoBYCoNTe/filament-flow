<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RoBYCoNTe\FilamentFlow\Concerns\HasDatabaseTransitions;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\NotifyingOrderState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\NotifyingPendingState;
use Spatie\ModelStates\HasStates;

/**
 * Test model for code-first notifications.
 *
 * @method static create(mixed $array_merge)
 *
 * @property mixed $state
 * @property CarbonInterface|Carbon|mixed $processed_at
 */
class NotifyingOrder extends Model
{
    use HasDatabaseTransitions;
    use HasStates;

    protected $table = 'test_orders';

    protected $fillable = [
        'order_number',
        'customer_name',
        'customer_email',
        'total_amount',
        'state',
        'user_id',
        'notes',
        'processing_notes',
        'shipping_notes',
        'tracking_number',
        'carrier',
        'estimated_delivery',
        'processed_at',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'state' => NotifyingOrderState::class,
        'total_amount' => 'decimal:2',
        'estimated_delivery' => 'date',
        'processed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Set default state on creation
        static::creating(function (self $model) {
            if (! $model->state) {
                $model->state = NotifyingPendingState::class;
            }
        });
    }

    /**
     * Get the owner of this order.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
