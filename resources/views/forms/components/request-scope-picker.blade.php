<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @include('filament-flow::forms.components.request-scope-tree', [
        'rows' => $getRows(),
        'statePath' => $getStatePath(),
    ])
</x-dynamic-component>
