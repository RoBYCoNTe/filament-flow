<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Support;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use RoBYCoNTe\FilamentFlow\Support\OpenRequest;

/**
 * Whether the answer to a request changed what the requester opened: the fields as they stand now
 * against what they held when the request was made.
 */
class OpenRequestChangeTest extends TestCase
{
    public function test_a_field_that_still_holds_its_snapshot_is_unchanged(): void
    {
        $request = $this->request(['title', 'amount'], ['title' => 'Frana', 'amount' => 100]);

        $this->assertSame(['amount'], $request->unchangedPaths(['title' => 'Frana corretta', 'amount' => 100]));
    }

    public function test_nested_paths_are_read_by_dot(): void
    {
        $request = $this->request(['works.title'], ['works.title' => 'Frana']);

        $this->assertSame([], $request->unchangedPaths(['works' => ['title' => 'Altro']]));
        $this->assertSame(['works.title'], $request->unchangedPaths(['works' => ['title' => 'Frana']]));
    }

    public function test_a_field_never_filled_and_a_field_cleared_are_the_same_value(): void
    {
        $request = $this->request(['note'], ['note' => null]);

        $this->assertSame(['note'], $request->unchangedPaths(['note' => '']));
        $this->assertSame(['note'], $request->unchangedPaths([]));
        $this->assertSame([], $request->unchangedPaths(['note' => 'Scritta']));
    }

    public function test_the_order_of_the_keys_of_a_value_does_not_count(): void
    {
        $request = $this->request(['place'], ['place' => ['city' => 'Lecce', 'province' => 'LE']]);

        $this->assertSame(['place'], $request->unchangedPaths(['place' => ['province' => 'LE', 'city' => 'Lecce']]));
        $this->assertSame([], $request->unchangedPaths(['place' => ['province' => 'BA', 'city' => 'Lecce']]));
    }

    public function test_a_list_that_changed_its_order_has_changed(): void
    {
        $request = $this->request(['files'], ['files' => [1, 2]]);

        $this->assertSame([], $request->unchangedPaths(['files' => [2, 1]]));
    }

    public function test_the_change_is_required_only_when_the_office_asked_for_it(): void
    {
        $this->assertTrue($this->request(['a'], ['a' => 1], requireChange: true)->requiresChange());
        $this->assertFalse($this->request(['a'], ['a' => 1])->requiresChange());
        $this->assertFalse($this->request([], [], requireChange: true)->requiresChange(), 'Senza campi non c’è nulla da cambiare.');
    }

    /**
     * @param  list<string>  $paths
     * @param  array<string, mixed>  $snapshot
     */
    private function request(array $paths, array $snapshot, bool $requireChange = false): OpenRequest
    {
        return new OpenRequest(
            transitionName: 'ask',
            label: null,
            fromState: 'a',
            toState: 'b',
            toStateLabel: null,
            note: null,
            deadline: null,
            requestedAt: Carbon::now(),
            requestedBy: null,
            scope: ['paths' => $paths, 'snapshot' => $snapshot, 'require_change' => $requireChange],
        );
    }
}
