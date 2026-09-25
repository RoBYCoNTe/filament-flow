<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Support;

use RoBYCoNTe\FilamentFlow\Support\FieldChanges;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The delta of two sets of values: which paths moved, and which did not — the comparison
 * behind the "changed fields" of the history.
 */
class FieldChangesTest extends TestCase
{
    public function test_a_map_is_opened_to_its_leaves(): void
    {
        $flat = FieldChanges::flatten([
            'applicant' => ['first_name' => 'Ada', 'email' => null],
            'total' => 5,
        ]);

        $this->assertSame([
            'applicant.first_name' => 'Ada',
            'applicant.email' => null,
            'total' => 5,
        ], $flat);
    }

    public function test_a_list_stays_whole(): void
    {
        $flat = FieldChanges::flatten([
            'documents' => ['report' => ['a.pdf', 'b.pdf']],
            'items' => [['amount' => 1], ['amount' => 2]],
        ]);

        $this->assertSame([
            'documents.report' => ['a.pdf', 'b.pdf'],
            'items' => [['amount' => 1], ['amount' => 2]],
        ], $flat);
    }

    public function test_only_the_paths_that_moved_are_kept(): void
    {
        $before = [
            'applicant' => ['first_name' => 'Ada', 'last_name' => 'Lovelace'],
            'note' => 'same',
            'untouched' => ['deep' => 'value'],
        ];

        $after = [
            'applicant' => ['first_name' => 'Ada Lovelace', 'last_name' => 'Lovelace'],
            'note' => 'same',
            'untouched' => ['deep' => 'value'],
        ];

        $this->assertSame([
            'applicant.first_name' => ['from' => 'Ada', 'to' => 'Ada Lovelace'],
        ], FieldChanges::between($before, $after));
    }

    public function test_a_path_that_appeared_or_left_is_a_change(): void
    {
        $changes = FieldChanges::between(['a' => 1], ['b' => 2]);

        $this->assertSame([
            'a' => ['from' => 1, 'to' => null],
            'b' => ['from' => null, 'to' => 2],
        ], $changes);
    }

    public function test_a_number_written_in_two_ways_is_not_a_change(): void
    {
        $this->assertSame([], FieldChanges::between(['total' => '5000.00'], ['total' => 5000.0]));
    }

    public function test_a_missing_value_and_an_empty_one_are_the_same(): void
    {
        $this->assertSame([], FieldChanges::between(['a' => null, 'b' => ''], ['a' => '', 'b' => null]));
        $this->assertSame([], FieldChanges::between(['a' => []], ['a' => null]));
    }

    public function test_a_boolean_and_a_truthy_value_are_the_same(): void
    {
        $this->assertSame([], FieldChanges::between(['flag' => true], ['flag' => 1]));
        $this->assertSame([], FieldChanges::between(['flag' => false], ['flag' => 0]));
        $this->assertNotSame([], FieldChanges::between(['flag' => true], ['flag' => false]));
    }

    public function test_a_payload_is_read_against_the_values_before_it_only_where_it_speaks(): void
    {
        $before = ['applicant' => ['first_name' => 'Ada', 'email' => 'ada@example.com'], 'note' => 'old'];

        // The payload names one path: what it does not mention did not move.
        $this->assertSame(
            ['applicant.first_name' => ['from' => 'Ada', 'to' => 'Grace']],
            FieldChanges::applied($before, ['applicant' => ['first_name' => 'Grace']]),
        );

        // A payload that repeats a value is not a change.
        $this->assertSame([], FieldChanges::applied($before, ['note' => 'old']));

        // A path that was not there appears: nothing before, a value now.
        $this->assertSame(
            ['applicant.vat_number' => ['from' => null, 'to' => '123']],
            FieldChanges::applied($before, ['applicant.vat_number' => '123']),
        );

        // The ignored paths stay out, here too.
        $this->assertSame([], FieldChanges::applied($before, ['applicant.email' => 'new@example.com'], ['applicant.*']));
    }

    public function test_ignored_paths_and_wildcards_are_left_out(): void
    {
        $changes = FieldChanges::between(
            ['applicant' => ['first_name' => 'Ada', 'email' => 'ada@example.com'], 'note' => 'old'],
            ['applicant' => ['first_name' => 'Ada Lovelace', 'email' => 'new@example.com'], 'note' => 'new'],
            ['applicant.email', 'note'],
        );

        $this->assertSame([
            'applicant.first_name' => ['from' => 'Ada', 'to' => 'Ada Lovelace'],
        ], $changes);

        $this->assertSame([], FieldChanges::between(
            ['applicant' => ['first_name' => 'Ada']],
            ['applicant' => ['first_name' => 'Grace']],
            ['applicant.*'],
        ));
    }
}
