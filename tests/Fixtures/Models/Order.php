<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoBYCoNTe\FilamentFlow\Concerns\HasDatabaseTransitions;
use RoBYCoNTe\FilamentFlow\Concerns\HasFlexibleStates;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateAccess;
use RoBYCoNTe\FilamentFlow\Concerns\HasWorkflowAssignments;
use RoBYCoNTe\FilamentFlow\Contracts\HasFieldLabels;
use RoBYCoNTe\FilamentFlow\Contracts\HasWorkflowLabel;
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
class Order extends Model implements HasFieldLabels, HasWorkflowLabel
{
    use HasDatabaseTransitions;
    use HasFlexibleStates;
    use HasStateAccess;
    use HasWorkflowAssignments;

    /** Labels used by the tests of the validation messages. */
    public function fieldLabel(string $path): ?string
    {
        return [
            'tracking_number' => 'Tracking number',
            'order_number' => 'Order number',
            'customer_email' => 'Customer e-mail',
            'customer_name' => 'Customer name',
        ][$path] ?? null;
    }

    /** How the order reads in a notification: the code beside the event. */
    public function workflowLabel(): ?string
    {
        return $this->order_number;
    }

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
        'form_data',
    ];

    protected $casts = [
        'state' => OrderState::class,
        'estimated_delivery' => 'date',
        'processed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'form_data' => 'array',
    ];

    /**
     * Get the owner of the order
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The same hand, under the name the owner column derives from `user_id`: a list that eager
     * loads it spares the owner column a query for every row.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
