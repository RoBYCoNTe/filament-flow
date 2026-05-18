<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoBYCoNTe\FilamentFlow\Concerns\HasDatabaseTransitions;
use RoBYCoNTe\FilamentFlow\Concerns\HasFlexibleStates;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateAccess;
use RoBYCoNTe\FilamentFlow\Concerns\HasWorkflowAssignments;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\OrderState;

/**
 * @property int|null $user_id
 * @property mixed $state
 * @property string $processing_notes
 * @property Carbon $processed_at
 * @property Carbon $shipped_at
 * @property string $tracking_number
 * @property string $carrier
 * @property Carbon $delivered_at
 * @property int $id
 * @property string $order_number
 * @property string $customer_name
 * @property string $customer_email
 * @property float $total_amount
 *
 * @method static create(array $array)
 * @method static find($orderId)
 * @method static where(string $string, string $class)
 * @method static whereIn(string $string, string[] $array)
 * @method static visibleTo(User $user)
 * @method static editableBy(User $user)
 */
class Order extends Model
{
    use HasDatabaseTransitions;
    use HasFlexibleStates;
    use HasStateAccess;
    use HasWorkflowAssignments;

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
        'state' => OrderState::class,
        'estimated_delivery' => 'date',
        'processed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /**
     * Get the owner of the order
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
