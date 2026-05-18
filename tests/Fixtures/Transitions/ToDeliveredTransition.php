<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\Transitions;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\DeliveredState;
use Spatie\ModelStates\Transition;

/**
 * Transition to Delivered State
 *
 * This transition demonstrates:
 * - Simple confirmation with optional notes
 * - Toggle field for boolean values
 * - Final state in workflow
 */
final class ToDeliveredTransition extends Transition
{
    public function __construct(
        private readonly Order $order,
        private readonly ?array $data = null
    ) {}

    /**
     * Execute the transition.
     */
    public function handle(): Order
    {
        // Change state
        $this->order->state = new DeliveredState($this->order);

        // Set timestamp
        $this->order->delivered_at = now();

        // Apply form data
        if ($this->data) {
            if (isset($this->data['delivery_notes'])) {
                $this->order->notes = ($this->order->notes ?? '')."\n[Delivery] ".$this->data['delivery_notes'];
            }

            if (isset($this->data['signature_received']) && $this->data['signature_received']) {
                $this->order->notes = ($this->order->notes ?? '')."\n[Signature received]";
            }
        }

        $this->order->save();

        return $this->order;
    }

    /**
     * Get notes to be saved in transition history.
     */
    public function getHistoryNotes(): ?string
    {
        $parts = [];

        // Add order reference from record
        $parts[] = sprintf('[%s] Delivered', $this->order->order_number);

        // Add tracking info from record
        if ($this->order->tracking_number) {
            $parts[] = sprintf('Tracking: %s', $this->order->tracking_number);
        }

        // Add signature status from form
        if (! empty($this->data['signature_received'])) {
            $parts[] = 'Signature: YES';
        }

        // Add delivery notes from form
        if (! empty($this->data['delivery_notes'])) {
            $parts[] = sprintf('Notes: %s', $this->data['delivery_notes']);
        }

        return ! empty($parts) ? implode(' | ', $parts) : null;
    }

    /**
     * Get the form schema for the transition.
     */
    public function form(): array
    {
        return [
            Toggle::make('signature_received')
                ->label('Signature Received')
                ->helperText('Check if customer signature was obtained'),

            Textarea::make('delivery_notes')
                ->label('Delivery Notes')
                ->rows(2)
                ->maxLength(500)
                ->placeholder('Any notes about the delivery...'),
        ];
    }

    /**
     * Determine if the form requires confirmation.
     */
    public function requiresConfirmation(): bool
    {
        return true;
    }

    /**
     * Only allow if order has been shipped
     */
    public function canTransition(): bool
    {
        return $this->order->shipped_at !== null && $this->order->tracking_number !== null;
    }
}
