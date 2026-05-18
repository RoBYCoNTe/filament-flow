<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\Transitions;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use Spatie\ModelStates\Transition;

/**
 * Transition to Processing State with Custom Form
 *
 * This transition demonstrates:
 * - Custom form fields in transition modal
 * - Data from form saved directly to model attributes
 * - Setting timestamps on transition
 */
final class ToProcessingTransition extends Transition
{
    public function __construct(
        private readonly Order $order,
        private readonly ?array $data = null
    ) {}

    /**
     * Execute the transition.
     *
     * This is called after validation passes.
     * Form data is available in $this->data.
     */
    public function handle(): Order
    {
        // Change state
        $this->order->state = new ProcessingState($this->order);

        // Set timestamp
        $this->order->processed_at = now();

        // Apply form data to model
        if ($this->data) {
            if (isset($this->data['processing_notes'])) {
                $this->order->processing_notes = $this->data['processing_notes'];
            }

            if (isset($this->data['estimated_delivery'])) {
                $this->order->estimated_delivery = $this->data['estimated_delivery'];
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

        // Add order reference from record
        $parts[] = sprintf('[%s]', $this->order->order_number);

        // Add customer info from record
        if ($this->order->customer_name) {
            $parts[] = sprintf('Customer: %s', $this->order->customer_name);
        }

        // Add processing notes from form
        if (! empty($this->data['processing_notes'])) {
            $parts[] = sprintf('Notes: %s', $this->data['processing_notes']);
        }

        // Add estimated delivery from form
        if (! empty($this->data['estimated_delivery'])) {
            $parts[] = sprintf('Est. Delivery: %s', $this->data['estimated_delivery']);
        }

        return ! empty($parts) ? implode(' | ', $parts) : null;
    }

    /**
     * Get the form schema for the transition.
     *
     * These fields will be shown in a modal when the transition action is triggered.
     * The data will be passed to handle() method.
     */
    public function form(): array
    {
        return [
            Textarea::make('processing_notes')
                ->label('Processing Notes')
                ->rows(3)
                ->maxLength(1000)
                ->placeholder('Enter any notes regarding the processing of this order...')
                ->helperText('Optional notes about the processing of the order.')
                ->required(),

            DatePicker::make('estimated_delivery')
                ->label('Estimated Delivery Date')
                ->minDate(now())
                ->helperText('When do you expect to deliver this order?'),
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
     * Custom validation can be added here
     */
    public function canTransition(): bool
    {
        // Example: Only allow transition if order total is greater than 0
        return $this->order->total_amount > 0;
    }
}
