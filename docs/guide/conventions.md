# Code conventions

Everything in this package — identifiers, comments, docblocks, these pages, the commit messages —
is written in **English**. A host application may speak any language to its users; the package
does not, and the two vocabularies must not meet in the middle of a file.

## What a class says about itself

Every class carries a docblock, and it answers four questions: what this is, why it exists, who
calls it, and what it must **not** do. The code already says what it does — the docblock says what
the reader cannot see.

```php
/**
 * The one door through which a transition is attempted.
 *
 * The page, the row action of the list and the simulator all pass from here, so that a refusal is
 * born once: the same rules, the same messages, the same order.
 */
```

A class that carries a flow — the definition cycle, the submission of an application, the
scheduled checks — describes the whole round trip in its header, in 8 to 12 lines, instead of
leaving the reader to reconstruct it from the callers. Cross-references join the steps:
`{@see WorkflowApplier}`, `{@see TransitionFormService}`.

## What a method says

phpDocumentor tags, in this order: a one-line summary, then the description of the *why*, then
`@param`, `@return`, `@throws`, `@see`.

**`@throws` is not optional.** An undocumented exception is a plan the caller cannot make — and
this package throws a family of them on purpose (`WorkflowNotFoundException`,
`InvalidStateException`, `AuthenticationRequiredException`, `FormulaConditionFailedException`, …).

Array shapes go wherever an array travels: `array{free: list<string>, assigned: list<string>}`
saves the reader a jump to the producer.

## The traps, written where they are

The most useful comment in the package is the one that says what went wrong before. The tenant is
the standing example: **without it the lookup does not fail, it returns `null`** — no error, no
exception — so every symptom looks like "nothing appears".

```php
// The tenant of the row is part of the question: the workflow of a call lives under the scheme
// that owns it, and without the tenant the service does not find it — which is how a dropdown of
// states comes out empty.
```

## Prose wraps at 96 columns; examples and tags never do

Pint formats code, not prose: wrap the text of a docblock by hand at 96 columns. Two things a
wrapping pass must leave alone. First, a usage example inside a docblock is there with its own
indentation, and joining its lines breaks its meaning. Second, **a tag is never re-flowed**: a
long `@param` or `@return` — an array shape, especially — stays on its line, because splitting it
turns a readable type into a syntax error for PHPStan. Both mistakes were made by hand here, and
`vendor/bin/phpstan analyse` is what caught them.

```php
/**
 *     Transition::make('approve', 'under_review', 'approved')
 *         ->requiresReason()
 *         ->validationRule(ValidationRule::make('assigned_amount')->rules('numeric|min:0'));
 */
```

## Every test says what it certifies

A test class opens with the behaviour it pins, in one paragraph, in the words of the host that
uses it — not with a list of methods. When a test exists because something broke, say so: the
story is what stops the bug from coming back.

## Keeping a documentation pass honest

A documentation pass must not move the code. One command proves it:

```bash
composer docs:comments              # every changed line of the working tree is a comment line
composer docs:comments src/Services # ... in one subtree
composer docs:comments --base main  # ... since a branch point, instead of the working tree
```

## The two guards

Two commands keep the documentation honest, and both run in CI:

```bash
composer docs:comments   # a documentation pass changed comment lines only
composer check:docs      # every public class is named by at least one page
```

The second one is a gate, not an advice: `docs-coverage` counts the public classes of `src/` that no
page ever mentions, and it fails while that number is not zero. Adding a service, a trait, a
relation manager or an enum means naming it somewhere — in a page, in a table, in a sentence. It is
the only thing that keeps these pages from becoming the photograph of last year.

## Before pushing

```bash
composer check:all   # Pint --test, PHPStan, PHPUnit
npm run docs:build   # the documentation site, which is what GitHub Pages publishes
```
