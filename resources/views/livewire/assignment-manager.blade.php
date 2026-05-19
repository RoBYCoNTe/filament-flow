<div class="space-y-3">
    {{-- Assignment list --}}
    @forelse($assignments as $assignment)
        @php
            $colors = ['bg-primary-500', 'bg-success-500', 'bg-warning-500', 'bg-danger-500', 'bg-info-500'];
            $colorIndex = crc32($assignment['name']) % count($colors);
            $typeCfg = $typeConfig[$assignment['assignment_type']] ?? $typeConfig['primary'];
        @endphp
        <div class="flex items-center gap-3 rounded-lg border p-3
            @if($assignment['has_overrides'])
                border-warning-300 bg-warning-50 dark:border-warning-600 dark:bg-warning-900/20
            @else
                border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800
            @endif
        ">
            {{-- Avatar --}}
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $colors[$colorIndex] }} text-xs font-semibold text-white">
                {{ $assignment['initials'] }}
            </div>

            {{-- User info --}}
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="truncate text-sm font-medium text-gray-950 dark:text-white">
                        {{ $assignment['name'] }}
                    </span>
                    @if($assignment['roles'])
                        <span class="truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ $assignment['roles'] }}
                        </span>
                    @endif
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                    {{-- Type badge — selectable for admins --}}
                    @if($canManage)
                        <div class="inline-flex items-center gap-0.5 rounded {{ $typeCfg['bg'] }}">
                            <x-filament::icon :icon="$typeCfg['icon']" class="ml-1.5 h-3 w-3 shrink-0" />
                            <select
                                wire:change="changeAssignmentType({{ $assignment['id'] }}, $event.target.value)"
                                class="border-0 bg-transparent py-0.5 pl-0.5 pr-1 text-[10px] font-semibold uppercase focus:ring-0 cursor-pointer"
                            >
                                @foreach($typeConfig as $typeKey => $typeMeta)
                                    <option value="{{ $typeKey }}" @selected($assignment['assignment_type'] === $typeKey)>
                                        {{ $typeMeta['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <span class="inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase {{ $typeCfg['bg'] }}">
                            <x-filament::icon :icon="$typeCfg['icon']" class="h-3 w-3" />
                            {{ $typeCfg['label'] }}
                        </span>
                    @endif

                    {{-- Custom metadata badges --}}
                    @includeWhen($assignmentBadgesView, $assignmentBadgesView ?? '', ['assignment' => $assignment])

                    {{-- Override badge --}}
                    @if($assignment['has_overrides'])
                        <span class="inline-flex items-center gap-0.5 rounded px-1 py-0.5 text-[10px] font-semibold uppercase bg-warning-100 text-warning-700 dark:bg-warning-400/20 dark:text-warning-400">
                            <x-filament::icon icon="heroicon-m-shield-exclamation" class="h-3 w-3" />
                            {{ __('filament-flow::messages.override') }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Override toggles --}}
            @if($canManage)
                <div class="flex shrink-0 items-center gap-1">
                    <button
                        type="button"
                        wire:click="toggleOverride({{ $assignment['id'] }}, 'view')"
                        class="inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium transition
                            @if($assignment['override_view'])
                                bg-warning-100 text-warning-700 dark:bg-warning-400/20 dark:text-warning-400
                            @else
                                bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500
                            @endif
                        "
                        title="{{ __('filament-flow::messages.view') }}"
                    >
                        <x-filament::icon icon="heroicon-m-eye" class="h-3.5 w-3.5" />
                    </button>
                    <button
                        type="button"
                        wire:click="toggleOverride({{ $assignment['id'] }}, 'edit')"
                        class="inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium transition
                            @if($assignment['override_edit'])
                                bg-warning-100 text-warning-700 dark:bg-warning-400/20 dark:text-warning-400
                            @else
                                bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500
                            @endif
                        "
                        title="{{ __('filament-flow::messages.edit') }}"
                    >
                        <x-filament::icon icon="heroicon-m-pencil-square" class="h-3.5 w-3.5" />
                    </button>
                    <button
                        type="button"
                        wire:click="toggleOverride({{ $assignment['id'] }}, 'transition')"
                        class="inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium transition
                            @if($assignment['override_transition'])
                                bg-warning-100 text-warning-700 dark:bg-warning-400/20 dark:text-warning-400
                            @else
                                bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500
                            @endif
                        "
                        title="{{ __('filament-flow::messages.transition') }}"
                    >
                        <x-filament::icon icon="heroicon-m-arrow-path" class="h-3.5 w-3.5" />
                    </button>

                    {{-- Remove button --}}
                    <button
                        type="button"
                        wire:click="removeAssignment({{ $assignment['id'] }})"
                        wire:confirm="{{ __('filament-flow::messages.confirm_remove_assignment') }}"
                        class="ml-1 inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium text-danger-500 hover:bg-danger-50 dark:hover:bg-danger-400/10 transition"
                        title="{{ __('filament-flow::messages.remove') }}"
                    >
                        <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                    </button>
                </div>
            @endif
        </div>
    @empty
        <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800/50">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-m-information-circle" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ __('filament-flow::messages.no_users_assigned') }}
                </p>
            </div>
        </div>
    @endforelse

    {{-- Add assignment form --}}
    @if($canManage)
        @if($showAddForm)
            <div class="rounded-lg border border-primary-200 bg-primary-50 p-3 space-y-3 dark:border-primary-700 dark:bg-primary-900/20">
                {{ $this->addForm }}

                {{-- Action buttons --}}
                <div class="flex items-center gap-2">
                    <x-filament::button
                        wire:click="addAssignment"
                        wire:loading.attr="disabled"
                        wire:target="addAssignment"
                        icon="heroicon-m-plus"
                        size="sm"
                    >
                        {{ __('filament-flow::messages.save') }}
                    </x-filament::button>
                    <x-filament::button
                        wire:click="toggleAddForm"
                        color="gray"
                        size="sm"
                    >
                        {{ __('filament-flow::messages.cancel') }}
                    </x-filament::button>
                </div>
            </div>
        @else
            <button
                type="button"
                wire:click="toggleAddForm"
                class="flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-500 hover:border-primary-400 hover:text-primary-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-primary-500 dark:hover:text-primary-400 transition"
            >
                <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                {{ __('filament-flow::messages.add_assignment') }}
            </button>
        @endif
    @endif
</div>
