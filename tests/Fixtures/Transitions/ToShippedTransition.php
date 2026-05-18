<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\Transitions;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ShippedState;
use RuntimeException;
use Spatie\ModelStates\Transition;

/**
 * Transition to Shipped State with User Assignment
 *
 * This transition demonstrates:
 * - Selecting users to assign during transition
 * - Multiple form fields with different types
 * - Validation that users are selected
 * - Complex business logic in transition
 */
final class ToShippedTransition extends Transition
{
    public function __construct(
        private readonly Order $order,
        private readonly ?array $data = null
    ) {}

    /**
     * Execute the transition.
     *
     * Validates assignments and applies shipping data.
     */
    public function handle(): Order
    {
        // Validate that users are assigned (either existing or from form)
        $assignedUserIds = $this->data['assigned_users'] ?? [];

        if (empty($assignedUserIds)) {
            // Check if there are already assigned users
            $existingAssignments = $this->order->getAssignedUserIds();
            if (empty($existingAssignments)) {
                throw new RuntimeException('At least one user must be assigned to ship the order.');
            }
        } else {
            // Assign the selected users as 'secondary' (shipping handlers)
            // Using 'secondary' since 'shipper' is not a valid enum value
            foreach ($assignedUserIds as $userId) {
                $this->order->assignTo($userId, 'secondary');
            }
        }

        // Change state
        $this->order->state = new ShippedState($this->order);

        // Set timestamp
        $this->order->shipped_at = now();

        // Apply form data to model
        if ($this->data) {
            if (isset($this->data['tracking_number'])) {
                $this->order->tracking_number = $this->data['tracking_number'];
            }

            if (isset($this->data['carrier'])) {
                $this->order->carrier = $this->data['carrier'];
            }

            if (isset($this->data['shipping_notes'])) {
                $this->order->shipping_notes = $this->data['shipping_notes'];
            }
        }

        $this->order->save();

        return $this->order;
    }

    /**
     * Get notes to be saved in transition history.
     *
     * Has access to:
     * - $this->order (the record being transitioned)
     * - $this->data (form data from the transition)
     */
    public function getHistoryNotes(): ?string
    {
        $parts = [];

        // Add order reference and amount from record
        $parts[] = sprintf('[%s] €%.2f', $this->order->order_number, $this->order->total_amount);

        // Add tracking info from form
        if (! empty($this->data['tracking_number'])) {
            $carrier = strtoupper($this->data['carrier'] ?? 'N/A');
            $parts[] = sprintf('Tracking: %s (%s)', $this->data['tracking_number'], $carrier);
        }

        // Add assigned users count
        $assignedCount = count($this->data['assigned_users'] ?? []);
        if ($assignedCount > 0) {
            $parts[] = sprintf('%d handler(s) assigned', $assignedCount);
        }

        // Add shipping notes from form
        if (! empty($this->data['shipping_notes'])) {
            $parts[] = sprintf('Notes: %s', $this->data['shipping_notes']);
        }

        return ! empty($parts) ? implode(' | ', $parts) : null;
    }

    /**
     * Get the form schema for the transition.
     *
     * Includes user selection and shipping details.
     */
    public function form(): array
    {
        return [
            Select::make('assigned_users')
                ->label('Assign Shipping Handlers')
                ->multiple()
                ->options(fn () => User::pluck('name', 'id')->toArray())
                ->searchable()
                ->preload()
                ->helperText('Select users responsible for shipping this order.')
                ->required(),

            TextInput::make('tracking_number')
                ->label('Tracking Number')
                ->maxLength(100)
                ->placeholder('Enter tracking number...')
                ->required(),

            Select::make('carrier')
                ->label('Carrier')
                ->options([
                    'ups' => 'UPS',
                    'fedex' => 'FedEx',
                    'dhl' => 'DHL',
                    'usps' => 'USPS',
                    'other' => 'Other',
                ])
                ->required(),

            Textarea::make('shipping_notes')
                ->label('Shipping Notes')
                ->rows(2)
                ->maxLength(500)
                ->placeholder('Any special shipping instructions...'),
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
     * Custom validation - order must be in processing state
     */
    public function canTransition(): bool
    {
        return $this->order->processed_at !== null;
    }
}
