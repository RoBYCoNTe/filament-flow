<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

enum SideEffectType: string
{
    case SetField = 'set_field';
    case SetTimestamp = 'set_timestamp';
    case ClearField = 'clear_field';
    case Increment = 'increment';
    case CustomClass = 'custom_class';
    case CreateChildApplication = 'create_child_application';
}
