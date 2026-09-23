<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

/**
 * The three questions a state answers about a record — may I view it, edit it, move it — plus
 * the one about creating a record of that model.
 */
enum AccessType: string
{
    case View = 'view';
    case Edit = 'edit';
    case Transition = 'transition';
    case Create = 'create';
}
