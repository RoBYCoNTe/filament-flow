{{--
    The handover history, drawn once and used by whoever shows it: the collapsible section of
    the panel, and the component a host places in a form.

    It expects `$history` (the list, most recent first) and `$timeline` (whether to string the
    entries on a line).
--}}
@php
    $timeline = $timeline ?? true;
    $colors = ['bg-primary-500', 'bg-success-500', 'bg-warning-500', 'bg-danger-500', 'bg-info-500'];
@endphp

@if (empty($history))
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('filament-flow::messages.ownership_history_empty') }}
    </p>
@else
    <ol class="space-y-4">
        @foreach ($history as $change)
            <li class="relative flex gap-3">
                @if ($timeline)
                    {{-- The line that strings the handovers: the last one has no need of it. --}}
                    @unless ($loop->last)
                        <span class="absolute left-4 top-9 h-[calc(100%-0.5rem)] w-px bg-gray-200 dark:bg-white/10"></span>
                    @endunless
                @endif

                <div class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white {{ $colors[crc32((string) $change['to']) % count($colors)] }}"
                     title="{{ $change['to'] }}"
                >
                    {{ $change['to_initials'] }}
                </div>

                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ $change['to'] }}
                        </span>

                        <x-filament::badge :color="$change['retained'] ? 'warning' : 'gray'" size="sm">
                            {{ $change['retention_label'] }}
                        </x-filament::badge>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-2 text-xs text-gray-500 dark:text-gray-400">
                        <span>
                            @if ($change['from'])
                                {{ __('filament-flow::messages.owner_change_from', ['name' => $change['from']]) }}
                            @else
                                {{ __('filament-flow::messages.owner_change_first') }}
                            @endif
                            · {{ $change['at'] }}
                        </span>

                        @if ($change['by'])
                            <span>· {{ __('filament-flow::messages.ownership_history_by', ['name' => $change['by']]) }}</span>
                        @endif
                    </div>

                    @if ($change['note'])
                        <p class="text-xs italic text-gray-600 dark:text-gray-300">
                            {{ $change['note'] }}
                        </p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@endif
