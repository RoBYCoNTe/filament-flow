<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

/**
 * How loud a notification is: it orders what the panel shows and words it accordingly.
 */
enum NotificationPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';
}
