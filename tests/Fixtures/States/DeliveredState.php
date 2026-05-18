<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\States;

class DeliveredState extends OrderState
{
    public function getLabel(): string
    {
        return 'Delivered';
    }

    public function getDescription(): string
    {
        return 'Order has been delivered';
    }

    public static function getSortOrder(): int
    {
        return 40;
    }
}
