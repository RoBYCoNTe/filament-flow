<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

enum NotificationPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';
}
