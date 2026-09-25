<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @php
        $label = $getStateLabel();
        $color = $getStateColor();
        $icon = $getStateMarkerIcon();
        $description = $getStateDescription();
        $isFinal = $getStateIsFinal();
        $extra = $getExtraLine();

        $chipClasses = match($color) {
            'primary' => 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30',
            'success' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30',
            'warning' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30',
            'danger'  => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30',
            'info'    => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-400/10 dark:text-info-400 dark:ring-info-400/30',
            'gray'    => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-700/40 dark:text-gray-300 dark:ring-gray-500/30',
            default   => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-700/40 dark:text-gray-300 dark:ring-gray-500/30',
        };

        // The mark of a state that is neither the beginning nor an end: a dot, when the
        // workflow named no icon of its own.
        $dotClasses = match($color) {
            'primary' => 'bg-primary-500',
            'success' => 'bg-success-500',
            'warning' => 'bg-warning-500',
            'danger'  => 'bg-danger-500',
            'info'    => 'bg-info-500',
            default   => 'bg-gray-400 dark:bg-gray-500',
        };
    @endphp

    @if($label)
        <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-1.5">
                <span
                    class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $chipClasses }}"
                    title="{{ $description ? $label.' — '.$description : $label }}"
                >
                    @if($icon)
                        <x-filament::icon :icon="$icon" class="h-3.5 w-3.5" aria-hidden="true" />
                    @elseif($showsStateDot())
                        <span class="h-1.5 w-1.5 rounded-full {{ $dotClasses }}" aria-hidden="true"></span>
                    @endif

                    {{ $label }}
                </span>

                @if($isFinal)
                    <span class="inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <x-filament::icon icon="heroicon-m-flag" class="h-3 w-3" aria-hidden="true" />
                        {{ __('filament-flow::messages.final_state') }}
                    </span>
                @endif
            </div>

            @if($description)
                <p class="max-w-prose text-xs text-gray-500 dark:text-gray-400">{{ $description }}</p>
            @endif

            @if($extra)
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $extra }}</p>
            @endif
        </div>
    @endif
</x-dynamic-component>
