<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

/**
 * When a scheduled check fires: an offset from a date of the record, a comparison between two
 * of its fields, or a rule the host provides.
 */
enum ScheduledCheckCondition: string
{
    case DateOffset = 'date_offset';
    case FieldCompare = 'field_compare';
    case CustomClass = 'custom_class';
}
