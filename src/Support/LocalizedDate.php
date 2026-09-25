<?php

namespace RoBYCoNTe\FilamentFlow\Support;

/**
 * How a date reads when nobody chose: the day before the month where the language belongs, the
 * month before the day where it does not.
 */
final class LocalizedDate
{
    public static function dateTime(): string
    {
        return app()->isLocale('it') ? 'd/m/Y H:i' : 'M j, Y H:i';
    }

    public static function date(): string
    {
        return app()->isLocale('it') ? 'd/m/Y' : 'M j, Y';
    }
}
