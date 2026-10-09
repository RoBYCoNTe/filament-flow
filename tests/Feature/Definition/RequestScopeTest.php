<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Definition;

use InvalidArgumentException;
use RoBYCoNTe\FilamentFlow\Definition\RequestScope;
use RoBYCoNTe\FilamentFlow\Definition\State;
use RoBYCoNTe\FilamentFlow\Definition\Transition;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowDefinition;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * What a request lets the office add — documents and opened fields — declared on the
 * transition that opens it and carried with the transition's own metadata.
 */
class RequestScopeTest extends TestCase
{
    public function test_a_scope_survives_the_round_trip_of_the_definition(): void
    {
        $scope = RequestScope::make()
            ->editableFields(only: ['applicant', 'documents'], except: ['applicant.fiscal_code'])
            ->attachments(accepts: ['PDF', '.pdf', 'docx'], max: 3, maxSizeMb: 8)
            ->requireSelection()
            ->requireChange()
            ->answeredBy('@owner')
            ->additive();

        $again = RequestScope::fromArray($scope->toArray());

        $this->assertSame($scope->toArray(), $again->toArray());
        $this->assertSame(['pdf', 'docx'], $again->toArray()['attachments']['accepts'], 'Types are normalised once.');
        $this->assertFalse($again->isExclusive());
        $this->assertTrue($again->requiresSelection());
        $this->assertTrue($again->requiresChange());
        $this->assertTrue($again->takesAttachments());
    }

    public function test_the_defaults_are_exclusive_with_no_attachments(): void
    {
        $scope = RequestScope::make();

        $this->assertTrue($scope->isExclusive());
        $this->assertFalse($scope->takesAttachments());
        $this->assertFalse($scope->requiresSelection());
        $this->assertFalse($scope->requiresChange());
        $this->assertSame('@owner', $scope->answeredByRole());
        $this->assertNull($scope->whitelist(), 'Everything can be chosen until the call narrows it.');
        $this->assertNull($scope->toArray()['attachments']);
    }

    public function test_the_whitelist_takes_the_descendants_and_the_exceptions(): void
    {
        $scope = RequestScope::make()->editableFields(
            only: ['documents', 'economic_framework'],
            except: ['documents.internal'],
        );

        $this->assertTrue($scope->allows('documents'));
        $this->assertTrue($scope->allows('documents.invoices'));
        $this->assertTrue($scope->allows('economic_framework.total'));
        $this->assertFalse($scope->allows('documents.internal'), 'An exception is out.');
        $this->assertFalse($scope->allows('documents.internal.notes'), 'And so is what lies under it.');
        $this->assertFalse($scope->allows('applicant'), 'Outside the whitelist.');
        $this->assertFalse($scope->allows('documentsX'), 'A prefix is not an ancestor.');
    }

    public function test_without_a_whitelist_everything_is_allowed_but_the_exceptions(): void
    {
        $scope = RequestScope::make()->editableFields(except: ['review']);

        $this->assertTrue($scope->allows('applicant.name'));
        $this->assertFalse($scope->allows('review.notes'));
    }

    public function test_a_transition_carries_its_scope_in_the_metadata(): void
    {
        $transition = Transition::make('request_integration', 'under_review', 'integration_requested')
            ->opensRequest()
            ->withRequestFields(noteField: 'review.notes')
            ->withRequestScope(RequestScope::make()->editableFields(only: ['documents'])->requireChange());

        $data = $transition->toArray();

        $this->assertSame('exclusive', $data['metadata']['request_scope']['mode']);
        $this->assertSame(['documents'], $data['metadata']['request_scope']['only']);
        $this->assertTrue($data['metadata']['opens_request']);
        $this->assertTrue($transition->requestScope()?->requiresChange());
    }

    public function test_a_scope_travels_through_the_workflow_definition(): void
    {
        $definition = WorkflowDefinition::make('scoped', 'App\\Models\\Dummy')
            ->state(State::make('under_review', 'Under review')->initial())
            ->state(State::make('integration_requested', 'Integration requested'))
            ->transition(
                Transition::make('request_integration', 'under_review', 'integration_requested')
                    ->opensRequest()
                    ->withRequestScope(RequestScope::make()->attachments()->editableFields(only: ['documents'])),
            );

        $again = WorkflowDefinition::fromArray($definition->toArray())->toArray();

        $declared = collect($again['transitions'])->firstWhere('name', 'request_integration')['metadata']['request_scope'] ?? null;

        $this->assertNotNull($declared);

        $scope = RequestScope::fromArray($declared);

        $this->assertTrue($scope->takesAttachments());
        $this->assertSame(['documents'], $scope->whitelist());
    }

    public function test_a_transition_without_a_scope_has_the_metadata_it_always_had(): void
    {
        $transition = Transition::make('request_integration', 'under_review', 'integration_requested')
            ->opensRequest()
            ->withRequestFields(noteField: 'review.notes');

        $this->assertNull($transition->requestScope());
        $this->assertArrayNotHasKey('request_scope', $transition->toArray()['metadata']);
    }

    public function test_a_scope_on_a_transition_that_opens_no_request_is_refused(): void
    {
        $transition = Transition::make('approve', 'under_review', 'approved')
            ->withRequestScope(RequestScope::make());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not open a request');

        $transition->toArray();
    }

    public function test_it_refuses_what_cannot_be_asked(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RequestScope::fromArray(['mode' => 'sometimes']);
    }

    public function test_attachments_need_a_type_and_a_positive_limit(): void
    {
        try {
            RequestScope::make()->attachments(accepts: []);
            $this->fail('A request that takes files must say which.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);

        RequestScope::make()->attachments(max: 0);
    }

    public function test_the_role_that_answers_cannot_be_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RequestScope::make()->answeredBy('  ');
    }
}
