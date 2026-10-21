@props(['annotations' => [], 'dateTimeFormat' => 'd/m/Y H:i'])

{{--
    What a transition left on its row of history, as a line of labelled values: a protocol number
    with the day it was given, a reference. It reads where the act is — next to who did it and
    when — and carries its own label, so it keeps reading the same way whatever the host changes.
--}}
@if ($annotations !== [])
    <ul data-annotations class="flex flex-wrap items-center gap-x-3 gap-y-1">
        @foreach ($annotations as $key => $annotation)
            @php
                $at = filled($annotation['at'] ?? null) ? \Illuminate\Support\Carbon::parse($annotation['at']) : null;
            @endphp

            <li data-annotation="{{ $key }}" class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-gray-500/20 dark:text-gray-300">
                <span class="font-medium text-gray-500 dark:text-gray-400">{{ $annotation['label'] ?? $key }}</span>
                <span class="font-semibold text-gray-900 dark:text-white">{{ $annotation['value'] ?? '' }}</span>
                @if ($at)
                    <span class="text-gray-500 dark:text-gray-400">· {{ $at->translatedFormat($dateTimeFormat) }}</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif
