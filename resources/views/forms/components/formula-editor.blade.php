<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        wire:ignore
        x-data="formulaEditor({
            state: $wire.$entangle('{{ $getStatePath() }}'),
            completionsUrl: {{ Js::from($getCompletionsUrl()) }},
            height: {{ Js::from($getHeight()) }},
        })"
    >
        <div
            x-ref="editor"
            class="rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600"
        ></div>

        @if($getCompletionsUrl())
        <div style="margin-top:0.375rem">
            <button
                type="button"
                @click="toggleVars()"
                class="ff-vars-toggle"
                :class="showVars ? 'open' : ''"
            >
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 4.707a1 1 0 011.414 0l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414-1.414L11.586 10 7.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                {{ __('Available variables') }}
            </button>

            <div x-show="showVars" x-cloak class="ff-vars-panel">
                <template x-for="v in vars" :key="v.name">
                    <div class="ff-var-row">
                        <div class="ff-var-header">
                            <span class="ff-var-name" x-text="v.name"></span>
                            <span class="ff-var-type" x-show="v.type" x-text="v.type"></span>
                            <span class="ff-var-desc" x-text="v.description ?? ''"></span>
                        </div>
                        <template x-if="(v.properties ?? []).length || (v.methods ?? []).length">
                            <div class="ff-var-members">
                                <template x-for="p in (v.properties ?? [])" :key="'p_' + p.name">
                                    <div class="ff-member-row">
                                        <span class="ff-prop-name" x-text="'.' + p.name"></span>
                                        <span class="ff-var-type" x-show="p.type" x-text="p.type"></span>
                                    </div>
                                </template>
                                <template x-for="m in (v.methods ?? [])" :key="'m_' + m.name">
                                    <div class="ff-member-row">
                                        <span class="ff-method-name" x-text="'.' + m.name + '()'"></span>
                                        <span class="ff-member-desc" x-show="m.description" x-text="m.description"></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
        @endif

        @if($getHint())
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {{ $getHint() }}
        </p>
        @endif
    </div>
</x-dynamic-component>
