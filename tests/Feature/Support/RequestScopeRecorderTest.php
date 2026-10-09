<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Support;

use RoBYCoNTe\FilamentFlow\Definition\RequestScope;
use RoBYCoNTe\FilamentFlow\Support\RequestScopeRecorder;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The pick of the requester, from the payload of the transition to the entry of the history.
 */
class RequestScopeRecorderTest extends TestCase
{
    private const KEY = RequestScopeRecorder::PAYLOAD_KEY;

    public function test_the_pick_leaves_the_payload_and_is_normalised(): void
    {
        $payload = [
            'review.notes' => 'Nota',
            self::KEY => ['paths' => [' documents. ', 'documents', 7, ''], 'attachments' => [3, 3, '', null, 'a1']],
        ];

        $this->assertSame(['review.notes' => 'Nota'], RequestScopeRecorder::withoutPick($payload));
        $this->assertSame(
            ['paths' => ['documents'], 'attachments' => [3, 'a1']],
            RequestScopeRecorder::pick($payload),
            'A path is trimmed once and repeated ones count once; what is not an id is dropped.',
        );
    }

    public function test_a_payload_with_no_pick_picks_nothing(): void
    {
        $this->assertSame(['paths' => [], 'attachments' => []], RequestScopeRecorder::pick(['a' => 1]));
        $this->assertSame(['paths' => [], 'attachments' => []], RequestScopeRecorder::pick([self::KEY => 'junk']));
    }

    public function test_it_names_what_the_call_does_not_allow(): void
    {
        $scope = RequestScope::make()->editableFields(only: ['documents'], except: ['documents.internal'])->requireSelection();

        $this->assertSame([], RequestScopeRecorder::errors($scope, [self::KEY => ['paths' => ['documents.invoices']]]));

        $errors = RequestScopeRecorder::errors($scope, [self::KEY => ['paths' => ['documents.internal', 'applicant']]]);

        $this->assertCount(2, $errors[self::KEY]);
        $this->assertSame(
            [self::KEY => ['Choose at least one field that can be changed.']],
            RequestScopeRecorder::errors($scope, []),
        );
    }

    public function test_attachments_need_the_declaration_and_respect_its_limit(): void
    {
        $this->assertNotSame([], RequestScopeRecorder::errors(RequestScope::make(), [self::KEY => ['attachments' => [1]]]));

        $scope = RequestScope::make()->attachments(max: 2);

        $this->assertSame([], RequestScopeRecorder::errors($scope, [self::KEY => ['attachments' => [1, 2]]]));
        $this->assertNotSame([], RequestScopeRecorder::errors($scope, [self::KEY => ['attachments' => [1, 2, 3]]]));
    }

    public function test_the_entry_freezes_the_values_of_the_chosen_fields(): void
    {
        $scope = RequestScope::make()->additive()->requireChange()->answeredBy('@assigned');

        $entry = RequestScopeRecorder::entry(
            $scope,
            [self::KEY => ['paths' => ['costs.total', 'missing'], 'attachments' => [9]]],
            ['costs' => ['total' => 120000]],
        );

        $this->assertSame([
            'mode' => 'additive',
            'paths' => ['costs.total', 'missing'],
            'snapshot' => ['costs.total' => 120000, 'missing' => null],
            'attachments' => [9],
            'require_change' => true,
            'answered_by' => '@assigned',
        ], $entry);
    }

    public function test_a_pick_that_opens_and_attaches_nothing_has_no_entry(): void
    {
        $this->assertNull(RequestScopeRecorder::entry(RequestScope::make(), [self::KEY => ['paths' => []]], []));
        $this->assertNull(RequestScopeRecorder::entry(RequestScope::make(), [], []));
    }
}
