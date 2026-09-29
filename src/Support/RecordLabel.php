<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\HasWorkflowLabel;
use Throwable;

/**
 * How a record reads in a sentence: the code a notification puts beside the event, so a list of
 * notifications does not read as a list of identical events.
 *
 * The host decides first (`HasWorkflowLabel`), then the title the record answers to (what
 * Filament shows for it), then a column that looks like a code. Nothing known, the engine says
 * nothing rather than a number nobody recognises.
 */
final class RecordLabel
{
    /** The attributes read when neither the host nor the record title has anything to say. */
    private const CODE_ATTRIBUTES = ['protocol_number', 'reference', 'code', 'number', 'name', 'title'];

    public static function of(Model $record): ?string
    {
        if ($record instanceof HasWorkflowLabel) {
            $label = $record->workflowLabel();

            if (self::isFilled($label)) {
                return trim((string) $label);
            }
        }

        if (method_exists($record, 'getRecordTitle')) {
            try {
                $title = $record->getRecordTitle();

                if (self::isFilled($title)) {
                    return trim((string) $title);
                }
            } catch (Throwable) {
                // A title that cannot be read is not a reason to lose the notification.
            }
        }

        foreach (self::CODE_ATTRIBUTES as $attribute) {
            $value = $record->getAttribute($attribute);

            if (is_int($value) || self::isFilled($value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    private static function isFilled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
