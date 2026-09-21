<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

enum ScheduledCheckFrequency: string
{
    case EveryMinute = 'every_minute';
    case EveryFiveMinutes = 'every_five_minutes';
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
}
