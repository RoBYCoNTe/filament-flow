<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

enum ScheduledCheckAction: string
{
    case Notification = 'notification';
    case Transition = 'transition';
    case SideEffect = 'side_effect';
}
