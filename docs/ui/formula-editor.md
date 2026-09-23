# Formula editor

The formula editor is the one place where the package documents itself **at runtime**. Instead of a
page listing the variables a formula may use, the editor asks the server which ones exist in the
scope it is editing, and offers them while the person types. A host that adds a variable gets it in
the editor without writing a line of documentation.

## The component

```php
use RoBYCoNTe\FilamentFlow\Forms\Components\FormulaEditorComponent;

FormulaEditorComponent::make('eligibility_formula')
    ->scope('workflow')            // which set of variables to complete from
    ->contextType(Order::class)    // the model being edited, so the scope knows what it is about
    ->contextId(fn () => $this->record?->getKey())
    ->height('240px');
```

The component renders a small editor (the JavaScript and the stylesheet ship with the package) and
asks the endpoint below for completions as the text changes.

## The endpoint

```
GET filament-flow/formula-completions      route: filament-flow.formula-completions
```

It is served by `RoBYCoNTe\FilamentFlow\Http\Controllers\FormulaCompletionsController`, and it is
registered with the `web` and `auth` middleware: a stranger cannot read the variables of your
application. It takes the **scope** and the **context** (the type and the id of the record being
edited), finds the provider registered for that scope and returns its payload. A scope nobody
registered is not a silence — the registry raises.

## The payload

`RoBYCoNTe\FilamentFlow\Support\CompletionPayload`:

| Field | What it holds |
|---|---|
| `variables` | The variables of the scope: name, kind, type, description, and their own properties and methods when they have them |
| `stringValues` | The values a variable may take — the states of a workflow, for instance |

## The scopes

`RoBYCoNTe\FilamentFlow\Support\FormulaCompletionRegistry` holds one provider per scope name. The
package registers one, **`workflow`**, whose provider is
`RoBYCoNTe\FilamentFlow\Support\WorkflowFormulaScope`: it completes from the workflow of the model
being edited — its states, its fields, its parameters.

## A scope of your own

1. Implement `RoBYCoNTe\FilamentFlow\Contracts\FormulaCompletionProvider`:

   ```php
   public function getCompletions(?Model $context): CompletionPayload;
   ```

2. Register it in a service provider of your own:

   ```php
   app(FormulaCompletionRegistry::class)->register('invoices', new InvoiceScope);
   ```

3. Ask for it from the form: `->scope('invoices')`.

To say **which fields** a formula may name, implement
`RoBYCoNTe\FilamentFlow\Contracts\FieldListProviderInterface` (`getFields(Model $context): array`).
The package asks that question where the completions are built, so a host that keeps its fields
somewhere unusual writes the list once instead of twice.

## Why it is worth knowing

Everything a formula can reach is decided by the **host**: the package offers the mechanism and the
one scope it owns, and the host adds the vocabulary of its own domain. That is why the completions
are served at runtime rather than written down here — and why the page you are reading describes the
mechanism instead of listing the variables, which would be out of date the day after it was written.
