<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

/**
 * How often a scheduled check is looked at: from every minute to every week.
 */
enum ScheduledCheckFrequency: string
{
    case EveryMinute = 'every_minute';
    case EveryFiveMinutes = 'every_five_minutes';
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
}
