<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use WeakMap;

/**
 * What a request has already worked out, kept for the rest of the request.
 *
 * A form is drawn field by field, and each field asks the same questions again: which state the
 * record is in, what a state allows, what the call looks like. Each answer is a query — a round trip
 * to a database that is often on another machine — and the same query asked three hundred times is
 * most of what a request costs. This is not a cache: nothing outlives the request, nothing is stored
 * anywhere, and it is the container that forgets it (`scoped`), at the end of a request or of a job.
 *
 * An entry must say **what it depends on**. A request-wide entry lists the models whose change makes
 * it stale: when one of them is saved or deleted through Eloquent the entry is dropped and worked out
 * again at the next question. An entry that depends on something Eloquent cannot see — the roles of a
 * person, which sit in a pivot table — says so by living with an **owner** instead: it is kept while
 * that object lives, and forgotten with it.
 *
 * What it cannot see: a write that does not go through a model (`DB::table()->update()`, a pivot
 * `attach`). Whoever writes that way and then asks again in the same request calls `flush()`.
 *
 * ```php
 * $state = Memo::instance()->remember(
 *     Memo::key('state', $workflow, $value),
 *     [Workflow::class, WorkflowState::class],
 *     fn () => $workflow->states()->where('name', $value)->first(),
 * );
 * ```
 *
 * It can be turned off — `filament-flow.memo.enabled` — and then every question is worked out again,
 * as it was before: for the day an answer looks stale in production.
 */
final class Memo
{
    /** @var array<string, array{value: mixed, depends: list<class-string>}> */
    private array $entries = [];

    /** @var WeakMap<object, array<string, array{value: mixed, depends: list<class-string>}>> */
    private WeakMap $owned;

    private int $hits = 0;

    private int $misses = 0;

    public function __construct()
    {
        $this->owned = new WeakMap;
    }

    /** The memory of the request (the container hands the same one to everybody until the request ends). */
    public static function instance(): self
    {
        return app(self::class);
    }

    /**
     * The answer to a question, worked out once.
     *
     * @param  list<class-string<Model>>  $dependsOn  the models whose change makes the answer stale; may be
     *                                                empty only for an entry with an `$owner`
     * @param  Closure(): mixed  $compute  works the answer out; `null` is an answer like any other
     * @param  object|null  $owner  keeps the entry while this object lives, instead of while the request does
     */
    public function remember(string $key, array $dependsOn, Closure $compute, ?object $owner = null): mixed
    {
        if ($owner === null && $dependsOn === []) {
            throw new InvalidArgumentException("The memo entry [{$key}] must say which models make it stale, or belong to an owner.");
        }

        if (! config('filament-flow.memo.enabled', true)) {
            return $compute();
        }

        $held = $owner === null ? ($this->entries[$key] ?? null) : ($this->owned[$owner][$key] ?? null);

        if ($held !== null) {
            $this->hits++;

            return $held['value'];
        }

        $this->misses++;

        $value = $compute();

        // Worked out inside the computation, an entry may already have been written: the answer
        // that was just worked out is the one that stays.
        if ($owner === null) {
            $this->entries[$key] = ['value' => $value, 'depends' => $dependsOn];
        } else {
            $entries = $this->owned[$owner] ?? [];
            $entries[$key] = ['value' => $value, 'depends' => $dependsOn];
            $this->owned[$owner] = $entries;
        }

        return $value;
    }

    /** Forget what was worked out about a model that changed: every entry that depends on its class. */
    public function invalidate(Model $model): void
    {
        $this->entries = array_filter(
            $this->entries,
            static fn (array $entry): bool => ! self::dependsOn($entry['depends'], $model),
        );

        foreach ($this->owned as $owner => $entries) {
            $this->owned[$owner] = array_filter(
                $entries,
                static fn (array $entry): bool => ! self::dependsOn($entry['depends'], $model),
            );
        }
    }

    public function forget(string $key): void
    {
        unset($this->entries[$key]);

        foreach ($this->owned as $owner => $entries) {
            unset($entries[$key]);
            $this->owned[$owner] = $entries;
        }
    }

    /** Forget everything: for whoever wrote without going through a model and asks again. */
    public function flush(): void
    {
        $this->entries = [];
        $this->owned = new WeakMap;
    }

    /**
     * What the memory did: how many questions it answered, and how many it had to work out.
     *
     * @return array{hits: int, misses: int, entries: int}
     */
    public function stats(): array
    {
        $owned = 0;

        foreach ($this->owned as $entries) {
            $owned += count($entries);
        }

        return ['hits' => $this->hits, 'misses' => $this->misses, 'entries' => count($this->entries) + $owned];
    }

    /**
     * A key from what the question is about: a model counts by its class and its key, a list by its
     * content, anything else by what it is.
     */
    public static function key(mixed ...$parts): string
    {
        return implode('|', array_map(static fn (mixed $part): string => match (true) {
            $part === null => '-',
            is_bool($part) => $part ? 'true' : 'false',
            is_scalar($part) => (string) $part,
            $part instanceof Model => $part::class.':'.$part->getKey(),
            is_array($part) => md5((string) json_encode($part)),
            is_object($part) => $part::class.'#'.spl_object_id($part),
            default => get_debug_type($part),
        }, $parts));
    }

    /**
     * @param  list<class-string>  $depends
     */
    private static function dependsOn(array $depends, Model $model): bool
    {
        foreach ($depends as $class) {
            if ($model instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
