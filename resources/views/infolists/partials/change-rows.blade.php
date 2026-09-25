{{-- The rows of a change: the field, the value it had, the value it took. --}}
<ul class="space-y-1">
    @foreach($rows as $row)
        <li class="flex flex-wrap items-baseline gap-x-1.5">
            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $row['label'] }}:</span>
            <span
                class="text-gray-400 line-through decoration-gray-300 dark:text-gray-500"
                title="{{ __('filament-flow::messages.previous_value') }}"
            >{{ $row['before']->toInlineString() }}</span>
            <x-filament::icon icon="heroicon-m-arrow-long-right" class="h-3 w-3 self-center text-gray-400" aria-hidden="true" />
            <span
                class="font-mono text-gray-900 dark:text-white"
                title="{{ __('filament-flow::messages.new_value') }}"
            >{{ $row['after']->toInlineString() }}</span>
        </li>
    @endforeach
</ul>
