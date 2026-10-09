<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Support;

use RoBYCoNTe\FilamentFlow\Support\OpenRequest;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * What the office picked, as a request tells it.
 */
class OpenRequestScopeTest extends TestCase
{
    public function test_a_request_without_a_pick_says_nothing_of_fields_or_files(): void
    {
        $request = $this->request(null);

        $this->assertFalse($request->hasScope());
        $this->assertSame([], $request->attachmentIds());
        $this->assertNull($request->toArray()['scope']);
    }

    public function test_fields_open_a_scope_and_files_alone_do_not(): void
    {
        $this->assertTrue($this->request(['paths' => ['amount'], 'attachments' => [1]])->hasScope());
        $this->assertFalse($this->request(['paths' => [], 'attachments' => [1]])->hasScope());
        $this->assertSame([1], $this->request(['paths' => [], 'attachments' => [1]])->attachmentIds());
    }

    /** @param array<string,mixed>|null $scope */
    private function request(?array $scope): OpenRequest
    {
        return new OpenRequest(
            transitionName: 'request_integration',
            label: null,
            fromState: 'under_review',
            toState: 'integration_requested',
            toStateLabel: null,
            note: null,
            deadline: null,
            requestedAt: null,
            requestedBy: null,
            scope: $scope,
        );
    }
}
