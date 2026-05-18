<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\States;

use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Transitions\NotifyingToProcessingTransition;
use Spatie\ModelStates\Exceptions\InvalidConfig;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Base state class for testing code-first notifications.
 */
abstract class NotifyingOrderState extends State
{
    abstract public function getLabel(): string;

    abstract public function getDescription(): string;

    /**
     * @throws InvalidConfig
     */
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(NotifyingPendingState::class)
            ->allowTransition(NotifyingPendingState::class, NotifyingProcessingState::class, NotifyingToProcessingTransition::class);
    }
}
