{{-- The values of a transition, each read in the shape the host gave it. --}}
<dl class="grid grid-cols-[minmax(0,10rem)_minmax(0,1fr)] gap-x-3 gap-y-1">
    @foreach($rows as $row)
        <dt class="text-gray-500 dark:text-gray-400">{{ $row['label'] }}</dt>
        <dd class="min-w-0 break-words text-gray-700 dark:text-gray-300">
            @switch($row['presentation']->format)
                @case(\RoBYCoNTe\FilamentFlow\Presentation\FieldFormat::Table)
                    <div class="overflow-x-auto rounded-md border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-xs">
                            <thead class="bg-white/60 dark:bg-gray-900/40">
                                <tr>
                                    @foreach($row['presentation']->value['columns'] as $heading)
                                        <th class="whitespace-nowrap px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">{{ $heading }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($row['presentation']->value['rows'] as $tableRow)
                                    <tr class="border-t border-gray-200 dark:border-gray-700">
                                        @foreach($row['presentation']->value['columns'] as $columnKey => $heading)
                                            <td class="px-2 py-1 align-top">{{ $tableRow[$columnKey] ?? '—' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @break
                @case(\RoBYCoNTe\FilamentFlow\Presentation\FieldFormat::Files)
                    <ul class="space-y-0.5">
                        @foreach($row['presentation']->value as $file)
                            <li>
                                @if($file['url'])
                                    <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="text-primary-600 underline dark:text-primary-400">{{ $file['name'] }}</a>
                                @else
                                    {{ $file['name'] }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @break
                @case(\RoBYCoNTe\FilamentFlow\Presentation\FieldFormat::Pairs)
                    <dl class="space-y-0.5">
                        @foreach($row['presentation']->value as $pairLabel => $pairValue)
                            <div class="flex gap-x-1.5">
                                <dt class="text-gray-500 dark:text-gray-400">{{ $pairLabel }}:</dt>
                                <dd>{{ $pairValue }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    @break
                @default
                    {{ $row['presentation']->toInlineString() }}
            @endswitch
        </dd>
    @endforeach
</dl>
