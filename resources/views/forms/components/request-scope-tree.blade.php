{{--
    The fields of a record as a compact tree the office ticks: the steps fold with a count of what is
    chosen inside, the fields of a section sit in a grid, a search narrows the list and a few commands
    work on the whole. State: the list of the paths ticked (`$statePath`), as a Livewire property.
--}}
<div
    x-data="{
        state: $wire.$entangle('{{ $statePath }}'),
        rows: {{ Js::from($rows) }},
        collapsed: {},
        query: '',
        init() {
            // The steps start folded, except the ones that already hold a choice.
            this.containers.forEach((row) => {
                if (row.depth === 0 && ! this.some(row.paths)) {
                    this.collapsed[row.id] = true
                }
            })
        },
        get containers() {
            return this.rows.filter((row) => row.kind !== 'field')
        },
        get allPaths() {
            return [...new Set(this.rows.filter((row) => row.kind === 'field').flatMap((row) => row.paths))]
        },
        get count() {
            return this.selected().size
        },
        get needle() {
            return this.query.trim().toLowerCase()
        },
        get nothingFound() {
            return this.needle !== '' && ! this.containers.some((row) => row.haystack.includes(this.needle))
        },
        selected() {
            return new Set(Array.isArray(this.state) ? this.state : [])
        },
        all(paths) {
            const selected = this.selected()

            return paths.length > 0 && paths.every((path) => selected.has(path))
        },
        some(paths) {
            const selected = this.selected()

            return paths.some((path) => selected.has(path))
        },
        chosen(paths) {
            const selected = this.selected()

            return paths.filter((path) => selected.has(path)).length
        },
        toggle(paths, on) {
            const selected = this.selected()

            paths.forEach((path) => (on ? selected.add(path) : selected.delete(path)))

            this.state = [...selected]
        },
        leavesOf(row) {
            return this.rows.filter((other) => other.parent === row.id && other.kind === 'field')
        },
        // A block shows when the search finds something in it, or — with no search — when none of
        // the blocks it sits in is folded.
        shown(row) {
            if (this.needle !== '') {
                return row.haystack.includes(this.needle)
            }

            let parent = row.parent

            while (parent !== null) {
                if (this.collapsed[parent]) {
                    return false
                }

                parent = this.rows[parent].parent
            }

            return true
        },
        open(row) {
            return this.needle !== '' || ! this.collapsed[row.id]
        },
        leafShown(row, leaf) {
            return this.needle === '' || row.label.toLowerCase().includes(this.needle) || leaf.label.toLowerCase().includes(this.needle)
        },
        expandAll() {
            this.collapsed = {}
        },
        collapseAll() {
            this.containers.forEach((row) => (this.collapsed[row.id] = true))
        },
    }"
    style="--u:1.625rem;border:1px solid #c5c7c9;border-radius:0;overflow:hidden"
>
    {{-- Search on the left, the commands on the right: they stay in view while the list scrolls. --}}
    <div
        style="display:flex;flex-wrap:wrap;align-items:center;gap:0.5rem;padding:0.5rem 0.75rem;border-bottom:1px solid #c5c7c9"
    >
        <div style="width:16rem;max-width:100%">
            <x-filament::input.wrapper inline-prefix prefix-icon="heroicon-m-magnifying-glass" style="border-radius:0">
                <x-filament::input
                    type="search"
                    x-model="query"
                    x-on:keydown.enter.prevent
                    placeholder="{{ __('Search fields') }}"
                />
            </x-filament::input.wrapper>
        </div>

        <div style="display:flex;flex-wrap:wrap;justify-content:flex-end;gap:0.375rem;margin-left:auto">
            <x-filament::button type="button" size="xs" color="gray" x-on:click="expandAll()">
                {{ __('Expand all') }}
            </x-filament::button>
            <x-filament::button type="button" size="xs" color="gray" x-on:click="collapseAll()">
                {{ __('Collapse all') }}
            </x-filament::button>
            <x-filament::button type="button" size="xs" color="gray" x-on:click="toggle(allPaths, true)">
                {{ __('Select all') }}
            </x-filament::button>
            <x-filament::button type="button" size="xs" color="gray" x-on:click="state = []">
                {{ __('Clear') }}
            </x-filament::button>
        </div>
    </div>

    <div style="max-height:55vh;overflow-y:auto;padding:0.5rem 0.75rem">
        <template x-for="row in containers" :key="row.id">
            <div x-show="shown(row)" :style="{ marginLeft: 'calc(var(--u) * ' + row.depth + ')' }">
                {{-- The header of a block: folds it, ticks everything under it, tells how much is chosen. --}}
                <div
                    style="display:flex;align-items:center;gap:0.5rem;padding:0.3125rem 0.5rem;cursor:pointer;border-radius:0;border-bottom:1px solid #e6e9f2"
                    :style="{ fontWeight: row.depth === 0 ? '700' : '600' }"
                    x-on:click="collapsed[row.id] = ! collapsed[row.id]"
                >
                    <span style="width:1.125rem;flex:none;text-align:center;line-height:1;color:#5c6f82" x-text="open(row) ? '▾' : '▸'"></span>

                    <input
                        type="checkbox"
                        class="fi-checkbox-input"
                        style="width:1.125rem;height:1.125rem;flex:none;cursor:pointer;border-radius:0"
                        x-bind:checked="all(row.paths)"
                        x-effect="$el.indeterminate = some(row.paths) && ! all(row.paths)"
                        x-on:click.stop
                        x-on:change="toggle(row.paths, $event.target.checked)"
                    />

                    <span style="flex:1;font-size:0.875rem;line-height:1.25rem" x-text="row.label"></span>

                    <span
                        style="font-size:0.75rem;font-weight:600;padding:0.0625rem 0.5rem;border-radius:0"
                        :style="some(row.paths)
                            ? { background: 'var(--color-primary-600, #0066cc)', color: '#fff' }
                            : { background: '#e6e9f2', color: '#5c6f82' }"
                        x-text="chosen(row.paths) + ' / ' + row.paths.length"
                    ></span>
                </div>

                {{-- The fields of the block, in as many columns as the dialog has room for. --}}
                <div x-show="open(row) && leavesOf(row).length">
                <div
                    style="display:grid;grid-template-columns:repeat(auto-fill,minmax(15rem,1fr));gap:0.25rem 0.75rem;padding:0.375rem 0 0.5rem var(--u)"
                >
                    <template x-for="leaf in leavesOf(row)" :key="leaf.id">
                        <div x-show="leafShown(row, leaf)">
                        <label
                            style="display:flex;align-items:center;gap:0.5rem;padding:0.3125rem 0.5rem;border-radius:0;cursor:pointer;font-size:0.875rem;line-height:1.25rem"
                            :style="all(leaf.paths)
                                ? { background: 'var(--color-primary-50, #f2f7fc)', boxShadow: 'inset 3px 0 0 var(--color-primary-600, #0066cc)' }
                                : { background: 'transparent', boxShadow: 'none' }"
                        >
                            <input
                                type="checkbox"
                                class="fi-checkbox-input"
                                style="width:1.125rem;height:1.125rem;flex:none;cursor:pointer;border-radius:0"
                                x-bind:checked="all(leaf.paths)"
                                x-on:change="toggle(leaf.paths, $event.target.checked)"
                            />
                            <span x-text="leaf.label"></span>
                        </label>
                        </div>
                    </template>
                </div>
                </div>
            </div>
        </template>

        <p x-show="nothingFound" x-cloak style="padding:0.5rem;font-size:0.875rem;color:#5c6f82">
            {{ __('No field matches the search.') }}
        </p>
    </div>

    {{-- What is chosen, at the bottom right. --}}
    <div
        style="display:flex;justify-content:flex-end;padding:0.5rem 0.75rem;border-top:1px solid #c5c7c9"
    >
        <span
            style="font-size:0.875rem;font-weight:600;white-space:nowrap"
            x-text="count + ' / ' + allPaths.length + ' ' + @js(__('fields chosen'))"
        ></span>
    </div>
</div>
