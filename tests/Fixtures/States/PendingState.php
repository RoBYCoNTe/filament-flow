<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\States;

class PendingState extends OrderState
{
    public function getLabel(): string
    {
        return 'Pending';
    }

    public function getDescription(): string
    {
        return 'Order is pending processing';
    }

    public static function getSortOrder(): int
    {
        return 10;
    }
}
