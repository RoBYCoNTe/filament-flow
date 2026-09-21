# Contributing to filament-flow

## Registering a formula scope

`FormulaEditorComponent` supports pluggable autocomplete via **formula scopes**.
A scope is a named set of variables (with properties and methods) that the editor
suggests to the user. The package ships with a built-in `workflow` scope; you can
register your own in any service provider.

### 1. Implement `FormulaCompletionProvider`

```php
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\FormulaCompletionProvider;
use RoBYCoNTe\FilamentFlow\Support\CompletionPayload;

final class MyFormulaScope implements FormulaCompletionProvider
{
    public function getCompletions(?Model $context): CompletionPayload
    {
        return new CompletionPayload(
            variables: [
                [
                    'name'        => 'scheme',
                    'kind'        => 'variable',
                    'type'        => 'Scheme',
                    'description' => 'The current scheme',
                    'properties'  => [
                        ['name' => 'slug',   'kind' => 'property', 'type' => 'string'],
                        ['name' => 'status', 'kind' => 'property', 'type' => 'string'],
                    ],
                    'methods' => [
                        [
                            'name'      => 'isOpen',
                            'kind'      => 'method',
                            'signature' => 'isOpen(): bool',
                            'description' => 'True if the scheme is currently open',
                        ],
                    ],
                ],
            ],
            stringValues: [
                // Keys are arbitrary group names; values are string literal suggestions
                // shown when the cursor is inside a quoted string.
                'statuses' => ['draft', 'published', 'closed'],
            ],
        );
    }
}
```

`$context` is the Eloquent model currently being edited (e.g. a `WorkflowTransition`
or `WorkflowTransitionSideEffect`). Use it to load context-specific suggestions such
as the states of the parent workflow or the fields of the current scheme. It may be
`null` when the record has not been saved yet.

### 2. Register the scope

In your `AppServiceProvider` (or any service provider that runs before the first
request), resolve `FormulaCompletionRegistry` and register your scope under a name:

```php
use RoBYCoNTe\FilamentFlow\Support\FormulaCompletionRegistry;

public function boot(): void
{
    $this->app->make(FormulaCompletionRegistry::class)
        ->register('scheme', new MyFormulaScope);
}
```

The name (`'scheme'` in this example) is the value you pass to `->scope()` on the
component.

### 3. Use the scope in a form component

```php
use RoBYCoNTe\FilamentFlow\Forms\Components\FormulaEditorComponent;

FormulaEditorComponent::make('opening_formula')
    ->label(__('Opening formula'))
    ->scope('scheme')
    ->contextType(Scheme::class)          // optional: model class for context
    ->contextId(fn ($component) => $component->getRecord()?->getKey())
    ->height('120px')
    ->hint(__('Use "now", "scheme.status", etc.'));
```

`contextType` + `contextId` tell the completions endpoint which model instance to
pass to `getCompletions()`. If omitted, `$context` will be `null`.

### CompletionPayload reference

| Field | Type | Description |
|---|---|---|
| `variables` | `array` | List of top-level variable descriptors (see below) |
| `stringValues` | `array<string, list<string>>` | String literal suggestions grouped by category |

**Variable descriptor:**

| Key | Required | Description |
|---|---|---|
| `name` | yes | Variable name shown in autocomplete (`now`, `user`, …) |
| `kind` | yes | `'variable'` \| `'property'` \| `'method'` |
| `type` | no | Human-readable type label (`DateTime`, `int`, …) |
| `description` | no | Short description shown in the tooltip panel |
| `properties` | no | Array of `{name, kind, type}` — suggested after a dot |
| `methods` | no | Array of `{name, kind, signature, description}` — suggested after a dot |
