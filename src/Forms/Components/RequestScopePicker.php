<?php

namespace RoBYCoNTe\FilamentFlow\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

/**
 * The fields of a record as a tree the requester ticks to say what the answering side may change.
 *
 * The state is the list of the paths ticked, the shape the request scope is recorded in: a
 * container ticks every path under it, a field only its own.
 */
class RequestScopePicker extends Field
{
    protected string $view = 'filament-flow::forms.components.request-scope-picker';

    /** @var array<int, array<string, mixed>>|Closure */
    protected array|Closure $tree = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

        // Only paths ever leave the dialog: whatever else the browser sent is dropped.
        $this->dehydrateStateUsing(static fn (mixed $state): array => array_values(array_unique(array_filter(
            (array) $state,
            static fn (mixed $path): bool => is_string($path) && $path !== '',
        ))));
    }

    /**
     * @param  array<int, array<string, mixed>>|Closure  $tree
     */
    public function tree(array|Closure $tree): static
    {
        $this->tree = $tree;

        return $this;
    }

    /**
     * The tree as the rows the view draws: each one with the row it hangs from, how deep it sits
     * and the paths it covers.
     *
     * @return list<array{id: int, parent: int|null, depth: int, label: string, kind: string, paths: list<string>, haystack?: string}>
     */
    public function getRows(): array
    {
        return self::rowsFor((array) $this->evaluate($this->tree));
    }

    /**
     * The rows of a tree, for whoever draws it without the field: a page that asks for the choice
     * in a panel of its own.
     *
     * @param  array<int, array<string, mixed>>  $tree
     * @return list<array{id: int, parent: int|null, depth: int, label: string, kind: string, paths: list<string>, haystack?: string}>
     */
    public static function rowsFor(array $tree): array
    {
        $rows = [];

        self::flatten($tree, null, 0, $rows);

        // What a container answers to in a search: its own label and the ones of the fields
        // under it, so the view can tell which blocks have a match without walking the tree.
        foreach ($rows as $index => $row) {
            if ($row['kind'] === 'field') {
                continue;
            }

            $labels = [$row['label']];

            foreach ($rows as $other) {
                if ($other['kind'] === 'field' && array_intersect($other['paths'], $row['paths']) !== []) {
                    $labels[] = $other['label'];
                }
            }

            $rows[$index]['haystack'] = mb_strtolower(implode(' ', $labels));
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $rows
     */
    private static function flatten(array $nodes, ?int $parent, int $depth, array &$rows): void
    {
        foreach ($nodes as $node) {
            $id = count($rows);

            $rows[] = [
                'id' => $id,
                'parent' => $parent,
                'depth' => $depth,
                'label' => (string) ($node['label'] ?? $node['key'] ?? ''),
                'kind' => (string) ($node['kind'] ?? 'field'),
                'paths' => array_values((array) ($node['paths'] ?? [])),
            ];

            self::flatten((array) ($node['children'] ?? []), $id, $depth + 1, $rows);
        }
    }
}
