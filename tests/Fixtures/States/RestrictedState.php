<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\States;

use RoBYCoNTe\FilamentFlow\Contracts\HasAccessRules;

/**
 * A state class that implements HasAccessRules for testing Code-First access control
 */
class RestrictedState extends OrderState implements HasAccessRules
{
    public function getLabel(): string
    {
        return 'Restricted';
    }

    public function getDescription(): string
    {
        return 'Order is in a restricted state with Code-First access rules';
    }

    public static function getSortOrder(): int
    {
        return 50;
    }

    /**
     * Only sales and admin roles can create records in this state
     */
    public static function getCreateAccessRules(): array
    {
        return ['role:sales,admin'];
    }

    /**
     * Only authenticated users can view
     */
    public static function getViewAccessRules(): array
    {
        return ['@authenticated'];
    }

    /**
     * Only owner or assigned users can edit
     */
    public static function getEditAccessRules(): array
    {
        return ['@owner', '@assigned'];
    }

    /**
     * Only managers and admins can transition
     */
    public static function getTransitionAccessRules(): array
    {
        return ['role:manager,admin'];
    }
}
