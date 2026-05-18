<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoBYCoNTe\FilamentFlow\Concerns\HasDatabaseTransitions;
use RoBYCoNTe\FilamentFlow\Concerns\HasWorkflowAssignments;

/**
 * Test model that uses plain string states (no Spatie State classes).
 * Mimics the behavior of App\Models\Claim in the main application.
 *
 * @property int $id
 * @property string $ticket_number
 * @property string $subject
 * @property string|null $description
 * @property string $state
 * @property string $priority
 * @property int|null $user_id
 * @property string|null $notes
 * @property string|null $resolution_notes
 *
 * @method static create(array $array)
 * @method static find($id)
 */
class Ticket extends Model
{
    use HasDatabaseTransitions;
    use HasWorkflowAssignments;

    protected $table = 'test_tickets';

    protected $fillable = [
        'ticket_number',
        'subject',
        'description',
        'state',
        'priority',
        'user_id',
        'notes',
        'resolution_notes',
    ];

    /**
     * Plain string cast — NO Spatie State class.
     * This is the key difference from Order.
     */
    protected $casts = [
        'state' => 'string',
    ];

    /**
     * Get the owner of the ticket.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
