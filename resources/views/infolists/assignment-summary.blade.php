<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @php
        $assignedUsers = $getAssignedUsersWithPermissions();
        $roleAccess = $getRoleAccess();

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
    @endphp

    <div class="space-y-3">
        {{-- Assigned users --}}
        @forelse($assignedUsers as $entry)
            <div class="flex items-center gap-3 rounded-lg border p-3
                @if($entry['has_overrides'])
                    border-warning-300 bg-warning-50 dark:border-warning-600 dark:bg-warning-900/20
                @else
                    border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800
                @endif
            ">
                {{-- Avatar --}}
                @php
                    $nameParts = explode(' ', trim($entry['user']->name));
                    $initials = count($nameParts) >= 2
                        ? mb_strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr(end($nameParts), 0, 1))
                        : mb_strtoupper(mb_substr($entry['user']->name, 0, 2));

                    $colors = ['bg-primary-500', 'bg-success-500', 'bg-warning-500', 'bg-danger-500', 'bg-info-500'];
                    $colorIndex = crc32($entry['user']->name) % count($colors);
                @endphp
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $colors[$colorIndex] }} text-xs font-semibold text-white">
                    {{ $initials }}
                </div>

                {{-- User info --}}
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="truncate text-sm font-medium text-gray-950 dark:text-white">
                            {{ $entry['user']->name }}
                        </span>
                        @if(method_exists($entry['user'], 'getRoleNames') && $entry['user']->getRoleNames()->isNotEmpty())
                            <span class="truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $entry['user']->getRoleNames()->implode(', ') }}
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                        <span>{{ ucfirst($entry['assignment_type']) }}</span>
                        @if($entry['has_overrides'])
                            <span class="inline-flex items-center gap-0.5 rounded px-1 py-0.5 text-[10px] font-semibold uppercase bg-warning-100 text-warning-700 dark:bg-warning-400/20 dark:text-warning-400">
                                <x-filament::icon icon="heroicon-m-shield-exclamation" class="h-3 w-3" />
                                {{ __('filament-flow::messages.override') }}
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Permission badges --}}
                <div class="flex shrink-0 items-center gap-1">
                    @if($entry['can_view'])
                        <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium
                            @if($entry['override_view'])
                                bg-warning-100 text-warning-700 dark:bg-warning-400/20 dark:text-warning-400
                            @else
                                bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300
                            @endif
                        " title="{{ __('filament-flow::messages.view') }}">
                            <x-filament::icon icon="heroicon-m-eye" class="h-3.5 w-3.5" />
                        </span>
                    @endif
                    @if($entry['can_edit'])
                        <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium
                            @if($entry['override_edit'])
                                bg-warning-100 text-warning-700 dark:bg-warning-400/20 dark:text-warning-400
                            @else
                                bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400
                            @endif
                        " title="{{ __('filament-flow::messages.edit') }}">
                            <x-filament::icon icon="heroicon-m-pencil-square" class="h-3.5 w-3.5" />
                        </span>
                    @endif
                    @if($entry['can_transition'])
                        <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium
                            @if($entry['override_transition'])
                                bg-warning-100 text-warning-700 dark:bg-warning-400/20 dark:text-warning-400
                            @else
                                bg-success-50 text-success-600 dark:bg-success-400/10 dark:text-success-400
                            @endif
                        " title="{{ __('filament-flow::messages.transition') }}">
                            <x-filament::icon icon="heroicon-m-arrow-path" class="h-3.5 w-3.5" />
                        </span>
                    @endif
                </div>
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

        {{-- Role access summary --}}
        @if($roleLabels->isNotEmpty())
            <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="mb-1.5 text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ __('filament-flow::messages.access_by_role') }}
                </p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($roleLabels as $role => $perms)
                        <span class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2 py-1 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            {{ $role }}
                            <span class="flex items-center gap-0.5 text-gray-400 dark:text-gray-500">
                                @if($perms['view'])
                                    <x-filament::icon icon="heroicon-m-eye" class="h-3 w-3" />
                                @endif
                                @if($perms['edit'])
                                    <x-filament::icon icon="heroicon-m-pencil-square" class="h-3 w-3" />
                                @endif
                                @if($perms['transition'])
                                    <x-filament::icon icon="heroicon-m-arrow-path" class="h-3 w-3" />
                                @endif
                            </span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-dynamic-component>
