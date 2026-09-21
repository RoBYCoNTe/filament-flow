<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

enum ScheduledCheckCondition: string
{
    case DateOffset = 'date_offset';
    case FieldCompare = 'field_compare';
    case CustomClass = 'custom_class';
}
