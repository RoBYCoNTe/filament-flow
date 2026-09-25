{{-- A name and a chevron: the summary of a folded group of fields. --}}
@php
    $summaryLabel = $group !== '' ? $group : __('filament-flow::messages.other_fields');
    $summaryCount = trans_choice('filament-flow::messages.fields_count', count($rows), ['count' => count($rows)]);
@endphp

<details
    class="group/flow-group mt-1.5 overflow-hidden rounded-md border border-gray-200 dark:border-gray-700"
    data-flow-group
>
    <summary class="flex cursor-pointer select-none list-none items-center gap-1.5 px-2.5 py-1.5 text-[11px] font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800/60">
        <x-filament::icon
            icon="heroicon-m-chevron-right"
            class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform group-open/flow-group:rotate-90"
            aria-hidden="true"
        />
        <span>{{ $summaryLabel }}</span>
        <span class="ml-auto shrink-0 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">
            {{ $summaryCount }}
        </span>
    </summary>

    <div class="border-t border-gray-200 px-2.5 py-2 dark:border-gray-700">
        @include($body)
    </div>
</details>
