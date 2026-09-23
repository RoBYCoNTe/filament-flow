{{--
    Il pannello delle assegnazioni, disegnato con i componenti di Filament: il bordo, lo
    sfondo e i colori sono quelli del tema, non nostre scelte. Le uniche classi proprie
    sono di tipografia e spaziatura.
--}}
<div class="space-y-3">
    @forelse($assignments as $assignment)
        @php
            $typeCfg = $typeConfig[$assignment['assignment_type']] ?? $typeConfig['primary'];
            $typeColor = match ($assignment['assignment_type']) {
                'primary' => 'primary',
                'secondary' => 'warning',
                default => 'gray',
            };
        @endphp

        <x-filament::section :heading="$assignment['name']" :description="$assignment['roles'] ?: null" compact>
            <div class="flex flex-wrap items-center gap-2">
                <x-filament::badge :color="$typeColor" :icon="$typeCfg['icon']">
                    {{ $typeCfg['label'] }}
                </x-filament::badge>

                @includeWhen($assignmentBadgesView, $assignmentBadgesView ?? '', ['assignment' => $assignment])

                @if($canManage)
                    @foreach(['view' => 'heroicon-m-eye', 'edit' => 'heroicon-m-pencil-square', 'transition' => 'heroicon-m-arrow-path'] as $kind => $icon)
                        <button
                            type="button"
                            wire:click="toggleOverride({{ $assignment['id'] }}, '{{ $kind }}')"
                            title="{{ __('filament-flow::messages.help_override_' . $kind) }}"
                        >
                            <x-filament::badge :color="$assignment['override_' . $kind] ? 'warning' : 'gray'" :icon="$icon">
                                {{ __('filament-flow::messages.' . $kind) }}
                            </x-filament::badge>
                        </button>
                    @endforeach
                @elseif($assignment['has_overrides'])
                    <x-filament::badge color="warning" icon="heroicon-m-shield-exclamation">
                        {{ __('filament-flow::messages.override') }}
                    </x-filament::badge>
                @endif

                @if($canManage)
                    <x-filament::icon-button
                        icon="heroicon-m-x-mark"
                        color="danger"
                        size="sm"
                        :label="__('filament-flow::messages.remove')"
                        wire:click="removeAssignment({{ $assignment['id'] }})"
                        wire:confirm="{{ __('filament-flow::messages.confirm_remove_assignment') }}"
                    />
                @endif
            </div>
        </x-filament::section>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('filament-flow::messages.no_users_assigned') }}
        </p>
    @endforelse

    @if($canManage)
        @if($showAddForm)
            <x-filament::section :heading="__('filament-flow::messages.add_assignment')" compact>
                <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('filament-flow::messages.help_access_overrides') }}
                </p>

                {{ $this->addForm }}

                <x-slot name="footer">
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
                        <x-filament::button wire:click="toggleAddForm" color="gray" size="sm">
                            {{ __('filament-flow::messages.cancel') }}
                        </x-filament::button>
                    </div>
                </x-slot>
            </x-filament::section>
        @else
            <x-filament::button wire:click="toggleAddForm" color="gray" outlined icon="heroicon-m-plus" size="sm">
                {{ __('filament-flow::messages.add_assignment') }}
            </x-filament::button>
        @endif
    @endif
</div>
