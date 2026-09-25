<?php

namespace RoBYCoNTe\FilamentFlow\Presentation;

use Illuminate\Support\Str;
use Stringable;

/**
 * How a value reads when nobody claimed it: the generic reading the engine gives every record.
 *
 * The shape decides the format — a map becomes labelled pairs, a list of maps becomes a table,
 * a list of scalars becomes a line, a boolean becomes yes or no — and the label comes from the
 * last step of the path, headlined. A host that knows better says so through
 * `HasFieldPresentation`; this is what remains when it does not.
 */
class DefaultFieldPresenter
{
    public function present(string $path, mixed $value, ?string $label = null, ?string $group = null): FieldPresentation
    {
        $label = ($label !== null && trim($label) !== '')
            ? $label
            : $this->labelFor($path);

        if ($value === null || $value === '' || $value === []) {
            return FieldPresentation::empty($label, $group);
        }

        if (is_bool($value)) {
            return FieldPresentation::text($label, $this->yesNo($value), $group);
        }

        if (is_array($value)) {
            return $this->presentArray($label, $value, $group);
        }

        if ($value instanceof Stringable) {
            return FieldPresentation::text($label, (string) $value, $group);
        }

        if (is_object($value)) {
            return FieldPresentation::text($label, $this->stringify($value), $group);
        }

        return FieldPresentation::text($label, (string) $value, $group);
    }

    /**
     * The name of a path nobody claimed: the words of the key, asked of the translations
     * first — a host that translates its own vocabulary («campo», «nome») has those words
     * written down already, and the path itself is the most precise key it can use.
     */
    private function labelFor(string $path): string
    {
        $last = (string) Str::afterLast($path, '.');
        $headline = Str::headline($last);

        foreach ([$path, $last, $headline] as $candidate) {
            $translated = __($candidate);

            if (is_string($translated) && $translated !== $candidate) {
                return $translated;
            }
        }

        return $headline;
    }

    /** @param array<array-key,mixed> $value */
    private function presentArray(string $label, array $value, ?string $group): FieldPresentation
    {
        if (array_is_list($value)) {
            $first = $value[0] ?? null;

            if (is_array($first)) {
                // A list of rows is a table: the columns are the keys the rows share, in the
                // order the first row declares them.
                return FieldPresentation::table(
                    $label,
                    $this->headings($value),
                    $this->rows($value),
                    $group,
                );
            }

            return FieldPresentation::text($label, implode(', ', array_map($this->stringify(...), $value)), $group);
        }

        return FieldPresentation::pairs($label, array_map($this->stringify(...), $value), $group);
    }

    /**
     * @param  list<array<array-key,mixed>>  $rows
     * @return array<string,string> headings keyed as the cells are
     */
    private function headings(array $rows): array
    {
        $keys = [];

        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                $keys[(string) $key] = Str::headline((string) $key);
            }
        }

        return $keys;
    }

    /**
     * @param  list<array<array-key,mixed>>  $rows
     * @return list<array<string,string>>
     */
    private function rows(array $rows): array
    {
        return array_map(
            fn (array $row): array => array_map($this->stringify(...), $row),
            $rows,
        );
    }

    private function yesNo(bool $value): string
    {
        return $value ? __('filament-flow::messages.yes') : __('filament-flow::messages.no');
    }

    private function stringify(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $this->yesNo($value);
        }

        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $json === false ? '—' : $json;
        }

        return (string) $value;
    }
}
