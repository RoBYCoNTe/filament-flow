<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

/**
 * What a scheduled check does when its condition holds: it notifies, it moves the record, or it
 * runs a side effect.
 */
enum ScheduledCheckAction: string
{
    case Notification = 'notification';
    case Transition = 'transition';
    case SideEffect = 'side_effect';
}
