# Validation

Every state of a workflow says what has to be true **in** it: which fields are
visible, which are frozen, which are required, and which rules their values have
to satisfy. A transition is the moment that state is left, so the transition is
where those rules are checked.

`WorkflowValidationService` is the single pass that runs them. The form, the API,
the console and your own code all go through it: there is no second
implementation to keep in sync.

## What it checks

| Source | Example |
|---|---|
| **Field permissions of the state** | `review.score` required, `costs.*` locked, `budget.requested_amount` readonly |
| **Transition rules** | `budget.requested_amount` `gte:1000`, with a custom message |
| **Host field rules** | the rules your application declares for its own fields (`FieldRuleSource`) |
| **Expression rules** | `form.review.score >= 60`, evaluated against the live form state |

The rules of the state being **left** are the ones that apply: they describe what
must hold to move on. The permissions of the target state are a rendering
concern — they apply as soon as the record is there.

A field that is **hidden** in the state is never required and never validated: a
value nobody can see cannot be missing.

## Declaring rules

```php
use RoBYCoNTe\FilamentFlow\Definition\ValidationRule;

$transition = Transition::make('approve', 'under_review', 'approved')
    // Plain Laravel rules
    ->validationRule(ValidationRule::make('project.end_date')->rules(['after:project.start_date']))
    // Custom message
    ->validationRule(ValidationRule::make('budget.requested_amount')
        ->rules(['gte:1000'])
        ->message('The requested contribution must be at least € 1.000,00.'))
    // Conditional: checked only when the condition holds
    ->validationRule(ValidationRule::make('documents.business_plan')
        ->rules(['required'])
        ->when('form.budget.requested_amount >= 50000'))
    // Expression: the rule *is* a formula
    ->validationRule(ValidationRule::make('review.score')
        ->expression('form.review.score >= 60')
        ->label('Score')
        ->message('The score must be at least 60 (current: {{ form.review.score }}).'));
```

Rules on the state itself are declared the same way, through the state fields:

```php
State::make('under_review', 'Under review')->fields([
    StateField::make('review.score')->required(),
    StateField::make('costs.amount')->readonly(),
    StateField::make('budget.requested_amount')->validationRules(['gte:1000']),
])
```

## Named rules

Rules whose name is registered in `ValidationRuleRegistry` are resolved as
closures, so a rule can be shared by the UI and the engine:

```php
app(ValidationRuleRegistry::class)->register('fiscal_code_checksum', $closure);
```

Any component — the package's Form Builder, a host form — can resolve the same
registry.

A named rule can take a parameter, written after the first colon: it arrives as
the fourth argument of the closure. It is what lets a rule talk about a single
item of a set instead of the field as a whole:

```php
app(ValidationRuleRegistry::class)->register(
    'attachment_uploaded',
    function (string $attribute, mixed $value, Closure $fail, ?string $parameters = null): void {
        [$key, $label] = explode('|', (string) $parameters, 2);

        if (! ($value[$key] ?? null)) {
            $fail("Attach {$label}.");
        }
    },
);

// A declaration in the database, on the field `documents`:
// 'attachment_uploaded:technical_appraisal|Technical appraisal'
```

## Host field rules

The engine cannot know where your field rules live. Implement the contract and
list it in the configuration:

```php
namespace App\Rules;

use RoBYCoNTe\FilamentFlow\Contracts\FieldRuleSource;

class SchemeFieldRuleSource implements FieldRuleSource
{
    public function rulesFor(Model $record, ?Model $user): array
    {
        return ['beneficiary.vat_number' => ['digits:11']];
    }
}
```

```php
// config/filament-flow.php
'validation' => [
    'rule_sources' => [\App\Rules\SchemeFieldRuleSource::class],
],
```

## Expressions

Expression rules (and the `condition` of a conditional rule) are evaluated by the
provider registered in the `FormulaConditionRegistry`, with the record **and the
live form state**:

```php
final class MyFormulaProvider implements FormulaConditionProvider
{
    public function evaluate(string $expression, Model $model, array $data = []): bool;
    public function interpolate(string $template, Model $model, array $data = []): string;
}
```

The package passes `$data` as a plain array; the provider decides how to expose
it. In this project it becomes the `form` variable, as an object graph, so
`form.review.score` works with the Symfony expression language (arrays would need
`form['review']['score']`).

## Running it

```php
use RoBYCoNTe\FilamentFlow\Services\WorkflowValidationService;

$result = app(WorkflowValidationService::class)->validatePayload(
    $record,
    auth()->user(),
    $payload,      // what the transition carries; merged over the stored values
    $transition,   // optional: its own rules
);

$result->isEmpty();     // true when everything holds
$result->errors();      // ['review.score' => ['The score must be at least 60.']]
$result->messages();    // [{path, label, message}] — what a summary renders
```

### On a transition

`transitionTo()`, `executeAction()` and every entry point built on them validate
before writing anything, and throw
`RoBYCoNTe\FilamentFlow\Exceptions\WorkflowValidationException` (a
`ValidationException`, so Laravel and Filament already know how to render it).

```php
use RoBYCoNTe\FilamentFlow\Exceptions\WorkflowValidationException;

try {
    $order->transitionTo('shipped');
} catch (WorkflowValidationException $exception) {
    $exception->errors();          // keyed by form path
    $exception->summary();         // flat list with labels
    $exception->firstErrorPath();
}
```

`forceTransitionTo()` is the explicit escape hatch: it skips validation on
purpose. Validation can also be turned off globally:

```php
'validation' => [
    'enabled' => true,
],
```

## Where the errors appear

The engine returns the errors keyed by form path, so the form shows them where
they belong: the field is painted in red, and the skeleton of the form points at
the step or the tab that contains it (the wizard opens on the step with the
first error, the affected tabs carry a red counter).

Errors on paths that have **no component** — a virtual key such as
`review.score`, or a field hidden in this state — cannot be painted inline:
`StateAction` lists them in the notification after the refused transition.

