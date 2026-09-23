<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

/**
 * When a notification leaves: at once, or after the delay the declaration gives it.
 */
enum NotificationTiming: string
{
    case Immediate = 'immediate';
    case Delayed = 'delayed';
}
