<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

enum AccessType: string
{
    case View = 'view';
    case Edit = 'edit';
    case Transition = 'transition';
    case Create = 'create';
}
