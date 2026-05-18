<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\States;

use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Transitions\ToDeliveredTransition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Transitions\ToProcessingTransition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Transitions\ToShippedTransition;
use Spatie\ModelStates\Exceptions\InvalidConfig;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class OrderState extends State
{
    abstract public function getLabel(): string;

    abstract public function getDescription(): string;

    /**
     * @throws InvalidConfig
     */
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingState::class)
            // Transition with custom form that saves processing_notes and estimated_delivery
            ->allowTransition(PendingState::class, ProcessingState::class, ToProcessingTransition::class)
            // Transition with user assignment and shipping details
            ->allowTransition(ProcessingState::class, ShippedState::class, ToShippedTransition::class)
            // Transition with delivery confirmation
            ->allowTransition(ShippedState::class, DeliveredState::class, ToDeliveredTransition::class);
    }
}
