{{-- Open or close every group of the section this sits in: one click to see it all. --}}
<span class="flex shrink-0 items-center gap-2" x-data>
    <button
        type="button"
        class="text-[11px] font-medium text-primary-600 hover:underline dark:text-primary-400"
        x-on:click="$el.closest('[data-flow-section]').querySelectorAll('details[data-flow-group]').forEach((detail) => detail.open = true)"
    >{{ __('filament-flow::messages.expand_all') }}</button>
    <button
        type="button"
        class="text-[11px] font-medium text-gray-500 hover:underline dark:text-gray-400"
        x-on:click="$el.closest('[data-flow-section]').querySelectorAll('details[data-flow-group]').forEach((detail) => detail.open = false)"
    >{{ __('filament-flow::messages.collapse_all') }}</button>
</span>
