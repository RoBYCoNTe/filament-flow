<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\States;

class ProcessingState extends OrderState
{
    public function getLabel(): string
    {
        return 'Processing';
    }

    public function getDescription(): string
    {
        return 'Order is being processed';
    }

    public static function getSortOrder(): int
    {
        return 20;
    }
}
