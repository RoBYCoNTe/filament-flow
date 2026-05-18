<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\States;

class ShippedState extends OrderState
{
    public function getLabel(): string
    {
        return 'Shipped';
    }

    public function getDescription(): string
    {
        return 'Order has been shipped';
    }

    public static function getSortOrder(): int
    {
        return 30;
    }
}
