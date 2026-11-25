<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Support;

use InvalidArgumentException;
use RoBYCoNTe\FilamentFlow\Support\Memo;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * What a request has already worked out: asked once, answered from memory for the rest of the
 * request, dropped when a model it depends on changes, and never a source of stale answers for what
 * Eloquent cannot see.
 */
class MemoTest extends TestCase
{
    private Memo $memo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memo = new Memo;
    }

    private function order(): Order
    {
        return Order::create(['order_number' => 'ORD-'.uniqid(), 'customer_name' => 'Customer', 'total_amount' => 10]);
    }

    public function test_a_question_is_worked_out_once(): void
    {
        $calls = 0;
        $work = function () use (&$calls): string {
            $calls++;

            return 'answer';
        };

        $this->assertSame('answer', $this->memo->remember('q', [Order::class], $work));
        $this->assertSame('answer', $this->memo->remember('q', [Order::class], $work));
        $this->assertSame(1, $calls);
        $this->assertSame(['hits' => 1, 'misses' => 1, 'entries' => 1], $this->memo->stats());
    }

    public function test_nothing_is_an_answer_too(): void
    {
        $calls = 0;
        $work = function () use (&$calls): ?string {
            $calls++;

            return null;
        };

        $this->memo->remember('q', [Order::class], $work);
        $this->memo->remember('q', [Order::class], $work);

        $this->assertSame(1, $calls, 'A question whose answer is nothing is not asked again.');
    }

    public function test_different_questions_do_not_share_an_answer(): void
    {
        $this->assertSame('a', $this->memo->remember('a', [Order::class], fn () => 'a'));
        $this->assertSame('b', $this->memo->remember('b', [Order::class], fn () => 'b'));
    }

    public function test_an_entry_for_the_request_must_say_what_makes_it_stale(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must say which models make it stale');

        $this->memo->remember('q', [], fn () => 'answer');
    }

    public function test_an_entry_of_an_owner_may_depend_on_nothing(): void
    {
        $owner = new \stdClass;

        $this->assertSame('answer', $this->memo->remember('q', [], fn () => 'answer', owner: $owner));
    }

    public function test_the_entries_of_two_owners_are_their_own(): void
    {
        $first = new \stdClass;
        $second = new \stdClass;

        $this->memo->remember('q', [], fn () => 'first', owner: $first);

        $this->assertSame('second', $this->memo->remember('q', [], fn () => 'second', owner: $second));
        $this->assertSame('first', $this->memo->remember('q', [], fn () => 'never', owner: $first));
    }

    public function test_an_entry_of_an_owner_does_not_outlive_it(): void
    {
        $owner = new \stdClass;
        $this->memo->remember('q', [], fn () => 'answer', owner: $owner);

        $this->assertSame(1, $this->memo->stats()['entries']);

        unset($owner);
        gc_collect_cycles();

        $this->assertSame(0, $this->memo->stats()['entries']);
    }

    public function test_a_model_that_changes_makes_stale_what_depends_on_its_class(): void
    {
        $calls = 0;
        $work = function () use (&$calls): int {
            return ++$calls;
        };

        $order = $this->order();
        app(Memo::class)->flush();

        $this->assertSame(1, app(Memo::class)->remember('q', [Order::class], $work));
        $this->assertSame(1, app(Memo::class)->remember('q', [Order::class], $work));

        $order->update(['customer_name' => 'Changed']);

        $this->assertSame(2, app(Memo::class)->remember('q', [Order::class], $work), 'The model changed: the answer is worked out again.');
    }

    public function test_a_model_that_is_deleted_makes_stale_what_depends_on_it(): void
    {
        $order = $this->order();
        $memo = app(Memo::class);
        $memo->flush();
        $calls = 0;

        $memo->remember('q', [Order::class], function () use (&$calls): int {
            return ++$calls;
        });
        $order->delete();
        $memo->remember('q', [Order::class], function () use (&$calls): int {
            return ++$calls;
        });

        $this->assertSame(2, $calls);
    }

    public function test_a_model_of_another_class_leaves_the_answer_alone(): void
    {
        $memo = app(Memo::class);
        $memo->flush();
        $calls = 0;
        $work = function () use (&$calls): int {
            return ++$calls;
        };

        $memo->remember('q', [Order::class], $work);
        $this->createTestUser(['email' => 'other@test.com']);
        $memo->remember('q', [Order::class], $work);

        $this->assertSame(1, $calls, 'Saving a user does not make an order stale.');
    }

    public function test_a_change_makes_stale_the_entries_of_an_owner_that_depend_on_it(): void
    {
        $memo = app(Memo::class);
        $memo->flush();
        $owner = new \stdClass;
        $calls = 0;
        $work = function () use (&$calls): int {
            return ++$calls;
        };

        $memo->remember('q', [Order::class], $work, owner: $owner);
        $this->order();
        $memo->remember('q', [Order::class], $work, owner: $owner);

        $this->assertSame(2, $calls);
    }

    public function test_an_entry_can_be_forgotten_and_everything_can_be_flushed(): void
    {
        $calls = 0;
        $work = function () use (&$calls): int {
            return ++$calls;
        };

        $this->memo->remember('a', [Order::class], $work);
        $this->memo->forget('a');
        $this->memo->remember('a', [Order::class], $work);
        $this->memo->flush();
        $this->memo->remember('a', [Order::class], $work);

        $this->assertSame(3, $calls);
    }

    public function test_it_can_be_turned_off_and_then_every_question_is_worked_out_again(): void
    {
        config(['filament-flow.memo.enabled' => false]);
        $calls = 0;
        $work = function () use (&$calls): int {
            return ++$calls;
        };

        $this->memo->remember('q', [Order::class], $work);
        $this->memo->remember('q', [Order::class], $work);

        $this->assertSame(2, $calls);
    }

    public function test_the_container_hands_the_same_memory_to_everybody_until_the_request_ends(): void
    {
        $first = app(Memo::class);

        $this->assertSame($first, app(Memo::class));
        $this->assertSame($first, Memo::instance());

        app()->forgetScopedInstances();

        $this->assertNotSame($first, app(Memo::class), 'A new request starts with a memory of its own.');
    }

    public function test_a_key_is_made_of_what_the_question_is_about(): void
    {
        $order = $this->order();
        $user = $this->createTestUser(['email' => 'key@test.com']);

        $this->assertSame('a|1|true|-', Memo::key('a', 1, true, null));
        $this->assertSame(Memo::key('x', $order, $user), Memo::key('x', $order->fresh(), $user->fresh()));
        $this->assertNotSame(Memo::key('x', $order), Memo::key('x', $user));
        $this->assertSame(Memo::key(['a' => 1]), Memo::key(['a' => 1]));
        $this->assertNotSame(Memo::key(['a' => 1]), Memo::key(['a' => 2]));
        $this->assertInstanceOf(User::class, $user);
    }
}
