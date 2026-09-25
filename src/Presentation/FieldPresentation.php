<?php

namespace RoBYCoNTe\FilamentFlow\Presentation;

/**
 * A field as a person should read it: its label, the shape of its value, the block it belongs
 * to, and whether the history should show it at all.
 *
 * A host that knows its own fields (a scheme, a form definition) says how each one reads; the
 * records are written in the words of the call, and the engine only draws them.
 */
final readonly class FieldPresentation
{
    /**
     * @param  string  $label  the name a person reads for the field
     * @param  FieldFormat  $format  how the value reads
     * @param  mixed  $value  Text: string|null; Pairs: array<string,string>; Table: array{columns: array<string,string>, rows: list<array<string,string>>} (columns are headings keyed as the rows are); Files: list<array{name:string,url:string|null}>
     * @param  string|null  $group  the block the field belongs to (a section, a step), used to group the history
     * @param  bool  $visible  false keeps the field out of the history entirely
     */
    public function __construct(
        public string $label,
        public FieldFormat $format = FieldFormat::Text,
        public mixed $value = null,
        public ?string $group = null,
        public bool $visible = true,
    ) {}

    public static function text(string $label, mixed $value = null, ?string $group = null): self
    {
        return new self($label, FieldFormat::Text, $value, $group);
    }

    /** @param array<string,string> $pairs */
    public static function pairs(string $label, array $pairs, ?string $group = null): self
    {
        return new self($label, FieldFormat::Pairs, $pairs, $group);
    }

    /**
     * @param  array<string,string>  $columns  the headings, keyed as the cells are
     * @param  list<array<string,string>>  $rows  one entry per row, cells keyed by column
     */
    public static function table(string $label, array $columns, array $rows, ?string $group = null): self
    {
        return new self($label, FieldFormat::Table, ['columns' => $columns, 'rows' => $rows], $group);
    }

    /** @param list<array{name:string,url:string|null}> $files */
    public static function files(string $label, array $files, ?string $group = null): self
    {
        return new self($label, FieldFormat::Files, $files, $group);
    }

    public static function empty(string $label, ?string $group = null): self
    {
        return new self($label, FieldFormat::Empty, null, $group);
    }

    /** A field that never belongs to the history: a content block, an internal key. */
    public static function hidden(string $label = '', ?string $group = null): self
    {
        return new self($label, FieldFormat::Empty, null, $group, visible: false);
    }

    /**
     * Nothing to read here: an empty format, or a format whose value carries no entry.
     */
    public function isEmpty(): bool
    {
        return match ($this->format) {
            FieldFormat::Empty => true,
            FieldFormat::Text => $this->value === null || $this->value === '' || $this->value === [],
            FieldFormat::Pairs => $this->value === null || $this->value === [],
            FieldFormat::Table => ($this->value['rows'] ?? []) === [],
            FieldFormat::Files => ($this->value ?? []) === [],
        };
    }

    /**
     * The whole value on one line: what a change row shows where the room is that of a line.
     */
    public function toInlineString(): string
    {
        return match ($this->format) {
            FieldFormat::Empty => '—',
            FieldFormat::Text => $this->inlineText(),
            FieldFormat::Pairs => implode(' · ', array_map(
                static fn (string $key, string $value): string => $key.': '.$value,
                array_keys((array) $this->value),
                array_values((array) $this->value),
            )),
            FieldFormat::Table => trans_choice(
                'filament-flow::messages.rows_count',
                count($this->value['rows'] ?? []),
                ['count' => count($this->value['rows'] ?? [])],
            ),
            FieldFormat::Files => trans_choice(
                'filament-flow::messages.files_count',
                count($this->value ?? []),
                ['count' => count($this->value ?? [])],
            ),
        };
    }

    private function inlineText(): string
    {
        if ($this->isEmpty()) {
            return '—';
        }

        return (string) $this->value;
    }
}
