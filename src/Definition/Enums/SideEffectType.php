<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

/**
 * What an effect does to a record: it writes a field, stamps a time, clears a field, increments
 * a number, calls a class of the host, or creates a child application.
 */
enum SideEffectType: string
{
    case SetField = 'set_field';
    case SetTimestamp = 'set_timestamp';
    case ClearField = 'clear_field';
    case Increment = 'increment';
    case CustomClass = 'custom_class';
    case CreateChildApplication = 'create_child_application';
}
