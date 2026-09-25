<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @php
        $assignedUsers = $getAssignedUsersWithPermissions();
        $roleAccess = $getRoleAccess();
        $stateLabel = $getStateLabel();
        $dateFormat = $getDateTimeFormat();

        // Collect unique role names with full access info
        $roleLabels = collect();
        foreach (['view', 'edit', 'transition'] as $type) {
            foreach ($roleAccess[$type] ?? [] as $role) {
                if (! $roleLabels->has($role)) {
                    $roleLabels[$role] = ['view' => false, 'edit' => false, 'transition' => false];
                }
                $roleLabels[$role] = array_merge($roleLabels[$role], [$type => true]);
            }
        }

        $typeConfig = $getTypeConfig();

        // What a person may do, in the order it is read: see, change, move on.
        $permissionConfig = [
            'view' => [
                'label' => __('filament-flow::messages.view'),
                'icon' => 'heroicon-m-eye',
                'on' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
            ],
            'edit' => [
                'label' => __('filament-flow::messages.edit'),
                'icon' => 'heroicon-m-pencil-square',
                'on' => 'bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400',
            ],
            'transition' => [
                'label' => __('filament-flow::messages.transition'),
                'icon' => 'heroicon-m-arrow-path',
                'on' => 'bg-success-50 text-success-600 dark:bg-success-400/10 dark:text-success-400',
            ],
        ];
    @endphp

    <div class="space-y-3">
        {{-- The permissions change with the state: the summary says which one it describes. --}}
        @if($stateLabel !== null && $assignedUsers->isNotEmpty())
            <p class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-m-flag" class="h-3.5 w-3.5" aria-hidden="true" />
                {{ __('filament-flow::messages.permissions_in_state', ['state' => $stateLabel]) }}
            </p>
        @endif

        {{-- Assigned users --}}
        @forelse($assignedUsers as $assignment)
            @php
                $nameParts = explode(' ', trim($assignment['user']->name));
                $initials = count($nameParts) >= 2
                    ? mb_strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr(end($nameParts), 0, 1))
                    : mb_strtoupper(mb_substr($assignment['user']->name, 0, 2));
                $typeCfg = $typeConfig[$assignment['assignment_type']] ?? $typeConfig['primary'];
            @endphp
            <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
                {{-- Avatar: the colour of the kind of assignment, not a colour of its own --}}
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $typeCfg['bg'] }} text-xs font-semibold">
                    {{ $initials }}
                </div>

                {{-- User info --}}
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                        <span class="truncate text-sm font-medium text-gray-950 dark:text-white">
                            {{ $assignment['user']->name }}
                        </span>
                        @foreach($assignment['roles'] as $roleLabel)
                            <span class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $roleLabel }}</span>
                        @endforeach
                    </div>

                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                        {{-- Type badge --}}
                        <span class="inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase {{ $typeCfg['bg'] }}">
                            <x-filament::icon :icon="$typeCfg['icon']" class="h-3 w-3" aria-hidden="true" />
                            {{ $typeCfg['label'] }}
                        </span>

                        {{-- Custom metadata badges --}}
                        @foreach($assignment['metadata_badges'] as $badge)
                            <span class="inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase {{ $badge['class'] ?? '' }}">
                                @if(!empty($badge['icon']))
                                    <x-filament::icon :icon="$badge['icon']" class="h-3 w-3" aria-hidden="true" />
                                @endif
                                {{ $badge['label'] }}
                            </span>
                        @endforeach

                        {{-- Override badge: the access that goes beyond the rules of the call --}}
                        @if($assignment['has_overrides'])
                            <span
                                class="inline-flex items-center gap-0.5 rounded px-1 py-0.5 text-[10px] font-semibold uppercase bg-warning-100 text-warning-700 dark:bg-warning-400/20 dark:text-warning-400"
                                title="{{ __('filament-flow::messages.help_access_overrides') }}"
                            >
                                <x-filament::icon icon="heroicon-m-shield-exclamation" class="h-3 w-3" aria-hidden="true" />
                                {{ __('filament-flow::messages.override') }}
                            </span>
                        @endif
                    </div>

                    {{-- When, and by whose hand --}}
                    @if($assignment['assigned_at'] !== null || $assignment['assigned_by'] !== null)
                        <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                            @if($assignment['assigned_at'] !== null)
                                <span class="inline-flex items-center gap-1">
                                    <x-filament::icon icon="heroicon-m-calendar-days" class="h-3.5 w-3.5" aria-hidden="true" />
                                    {{ __('filament-flow::messages.assigned_at', ['date' => $assignment['assigned_at']->translatedFormat($dateFormat)]) }}
                                </span>
                            @endif
                            @if($assignment['assigned_by'] !== null)
                                <span class="inline-flex items-center gap-1">
                                    <x-filament::icon icon="heroicon-m-user-circle" class="h-3.5 w-3.5" aria-hidden="true" />
                                    {{ __('filament-flow::messages.assigned_by', ['name' => $assignment['assigned_by']]) }}
                                </span>
                            @endif
                        </p>
                    @endif
                </div>

                {{-- What this person may do, and what they may not: the three always stand
                     together, so two people can be compared at a glance. --}}
                <div class="flex shrink-0 items-center gap-1">
                    @foreach($permissionConfig as $permission => $config)
                        @php
                            $granted = (bool) $assignment['can_'.$permission];
                            $override = (bool) $assignment['override_'.$permission];
                            $label = $config['label'].($granted ? '' : ' — '.__('filament-flow::messages.denied'));
                        @endphp
                        <span
                            class="inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium
                                @if($override)
                                    bg-warning-100 text-warning-700 dark:bg-warning-400/20 dark:text-warning-400
                                @elseif($granted)
                                    {{ $config['on'] }}
                                @else
                                    bg-gray-50 text-gray-300 dark:bg-gray-800/60 dark:text-gray-600
                                @endif
                            "
                            role="img"
                            aria-label="{{ $label }}"
                            title="{{ $granted && $override ? $config['label'].' ('.__('filament-flow::messages.override').')' : $label }}"
                        >
                            <x-filament::icon :icon="$config['icon']" class="h-3.5 w-3.5" aria-hidden="true" />
                        </span>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-m-information-circle" class="h-4 w-4 text-gray-400 dark:text-gray-500" aria-hidden="true" />
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ __('filament-flow::messages.no_users_assigned') }}
                    </p>
                </div>
            </div>
        @endforelse

        {{-- Role access summary --}}
        @if($roleLabels->isNotEmpty())
            <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="mb-1.5 text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ __('filament-flow::messages.access_by_role') }}
                </p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($roleLabels as $role => $perms)
                        <span class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2 py-1 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            {{ $getRoleLabel($role) }}
                            <span class="flex items-center gap-0.5 text-gray-400 dark:text-gray-500">
                                @foreach($permissionConfig as $permission => $config)
                                    @if($perms[$permission])
                                        <span role="img" aria-label="{{ $config['label'] }}" title="{{ $config['label'] }}">
                                            <x-filament::icon :icon="$config['icon']" class="h-3 w-3" aria-hidden="true" />
                                        </span>
                                    @endif
                                @endforeach
                            </span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-dynamic-component>
