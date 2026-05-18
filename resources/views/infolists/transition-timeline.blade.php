<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @php
        $timeline = $getTimeline();
        $totalCount = $getTotalCount();
        $limit = $getLimit();
    @endphp

    <div class="space-y-3">
        @forelse($timeline as $entry)
            <div class="flex gap-3 text-sm">
                {{-- Timeline dot --}}
                <div class="flex flex-col items-center">
                    <div class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full
                        @if($entry->from_state === $entry->to_state) bg-gray-400 dark:bg-gray-500
                        @else bg-primary-500
                        @endif
                    "></div>
                    @unless($loop->last)
                        <div class="h-full w-px bg-gray-200 dark:bg-gray-700"></div>
                    @endunless
                </div>

                {{-- Content --}}
                <div class="min-w-0 flex-1 pb-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="font-medium text-gray-950 dark:text-white truncate">
                            @if($entry->from_state === $entry->to_state)
                                {{ $entry->transition?->label ?? __('Action') }}
                            @else
                                @if($entry->from_state_label && $entry->to_state_label)
                                    {{ $entry->from_state_label }} → {{ $entry->to_state_label }}
                                @else
                                    {{ class_basename($entry->from_state ?? '') }} → {{ class_basename($entry->to_state ?? '') }}
                                @endif
                            @endif
                        </div>
                        <time class="shrink-0 text-xs text-gray-500 dark:text-gray-400" datetime="{{ $entry->created_at->toIso8601String() }}">
                            {{ $entry->created_at->diffForHumans() }}
                        </time>
                    </div>

                    @if($entry->user_name)
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $entry->user_name }}
                        </p>
                    @endif

                    @if($entry->notes)
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-300 italic">
                            {{ Str::limit($entry->notes, 120) }}
                        </p>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                {{ __('filament-flow::messages.no_history_yet') }}
            </p>
        @endforelse

        @if($totalCount > $limit)
            <p class="text-xs text-gray-400 dark:text-gray-500 text-center">
                {{ __('filament-flow::messages.count_more_entries', ['count' => $totalCount - $limit]) }}
            </p>
        @endif
    </div>
</x-dynamic-component>
