<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Livewire;

use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use RoBYCoNTe\FilamentFlow\Events\WorkflowOwnerChanged;
use RoBYCoNTe\FilamentFlow\Livewire\AssignmentManager;
use RoBYCoNTe\FilamentFlow\Models\WorkflowOwnerChange;
use RoBYCoNTe\FilamentFlow\Support\TransferTargets;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Gates\ReviewerAccessGate;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Transfer\NewPersonTransferTarget;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Handing a record to a person the panel does not list yet: the host names a resolver, the form
 * asks for what the resolver declares, and the handover runs as any other — with whatever the
 * resolver wants kept beside it. Without a resolver, nothing of this exists.
 */
class AssignmentManagerTransferTargetTest extends TestCase
{
    protected Order $order;

    protected User $admin;

    protected User $listed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']);
        $this->listed = $this->createTestUser(['email' => 'listed@test.com']);

        $this->order = Order::create([
            'order_number' => 'ORD-TT-001',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin);
    }

    private function withResolver(): void
    {
        config(['filament-flow.state_access.transfer_target_resolver' => NewPersonTransferTarget::class]);
    }

    private function panel(array $parameters = []): Testable
    {
        return Livewire::test(AssignmentManager::class, ['record' => $this->order, ...$parameters]);
    }

    public function test_without_a_resolver_the_form_asks_only_for_a_listed_person(): void
    {
        $this->assertNull(TransferTargets::resolver());

        $this->panel()
            ->call('toggleTransferForm')
            ->assertDontSee('New person')
            ->assertSee(__('filament-flow::messages.new_owner'));
    }

    public function test_with_a_resolver_the_form_offers_the_new_person(): void
    {
        $this->withResolver();

        $this->panel()
            ->call('toggleTransferForm')
            ->assertSee('New person')
            ->assertSee(__('filament-flow::messages.transfer_target_existing'));
    }

    public function test_a_listed_person_is_still_chosen_when_the_resolver_is_there(): void
    {
        $this->withResolver();

        $this->panel()
            ->set('transferFormData.newOwnerId', $this->listed->id)
            ->call('transferOwnership')
            ->assertNotified();

        $this->assertSame($this->listed->id, $this->order->fresh()->user_id);
    }

    public function test_the_record_goes_to_the_person_the_resolver_makes(): void
    {
        $this->withResolver();
        Event::fake([WorkflowOwnerChanged::class]);

        $this->panel()
            ->set('transferFormData.targetMode', AssignmentManager::TARGET_NEW)
            ->set('transferFormData.newPerson.name', 'Piero Montalbano')
            ->set('transferFormData.newPerson.email', 'piero@test.com')
            ->set('transferFormData.retention', 'viewer')
            ->call('transferOwnership')
            ->assertNotified();

        $person = User::query()->where('email', 'piero@test.com')->first();

        $this->assertNotNull($person);
        $this->assertSame($person->id, $this->order->fresh()->user_id);
        $this->assertSame(1, $this->order->assignments()->where('user_id', $this->admin->id)->count());

        $change = WorkflowOwnerChange::query()->forRecord($this->order)->first();
        $this->assertSame($person->id, $change->to_user_id);
        $this->assertSame(['provisioned' => true, 'provisioned_by' => $this->admin->id], $change->metadata);

        Event::assertDispatched(WorkflowOwnerChanged::class, fn (WorkflowOwnerChanged $event): bool => $event->toUserId === $person->id
            && $event->metadata === ['provisioned' => true, 'provisioned_by' => $this->admin->id]);
    }

    public function test_a_field_of_the_new_person_that_is_missing_stops_the_handover(): void
    {
        $this->withResolver();

        $this->panel()
            ->set('transferFormData.targetMode', AssignmentManager::TARGET_NEW)
            ->set('transferFormData.newPerson.name', 'Piero Montalbano')
            ->call('transferOwnership')
            ->assertHasErrors(['transferFormData.newPerson.email' => 'required']);

        $this->assertSame($this->admin->id, $this->order->fresh()->user_id);
        $this->assertSame(0, WorkflowOwnerChange::query()->forRecord($this->order)->count());
    }

    public function test_the_resolver_can_refuse_a_value_and_nothing_is_left_behind(): void
    {
        $this->withResolver();

        $this->panel()
            ->set('transferFormData.targetMode', AssignmentManager::TARGET_NEW)
            ->set('transferFormData.newPerson.name', 'Piero Montalbano')
            ->set('transferFormData.newPerson.email', 'piero@blocked.test')
            ->call('transferOwnership')
            ->assertHasErrors(['transferFormData.newPerson.email']);

        $this->assertSame($this->admin->id, $this->order->fresh()->user_id);
        $this->assertNull(User::query()->where('email', 'piero@blocked.test')->first());
    }

    public function test_a_person_who_already_holds_the_record_is_refused_and_not_left_created(): void
    {
        $this->withResolver();
        $this->order->update(['user_id' => $this->listed->id]);

        $this->panel()
            ->set('transferFormData.targetMode', AssignmentManager::TARGET_NEW)
            ->set('transferFormData.newPerson.name', 'Listed')
            ->set('transferFormData.newPerson.email', 'listed@test.com')
            ->call('transferOwnership')
            ->assertHasErrors(['transferFormData.newOwnerId']);

        $this->assertSame(0, WorkflowOwnerChange::query()->forRecord($this->order)->count());
    }

    public function test_who_may_not_manage_the_access_cannot_make_a_person(): void
    {
        $this->withResolver();

        $this->panel(['gate' => ReviewerAccessGate::class])
            ->set('transferFormData.targetMode', AssignmentManager::TARGET_NEW)
            ->set('transferFormData.newPerson.name', 'Piero Montalbano')
            ->set('transferFormData.newPerson.email', 'piero@test.com')
            ->call('transferOwnership');

        $this->assertNull(User::query()->where('email', 'piero@test.com')->first());
        $this->assertSame($this->admin->id, $this->order->fresh()->user_id);
    }

    public function test_a_resolver_that_is_not_a_resolver_is_refused_with_a_clear_message(): void
    {
        config(['filament-flow.state_access.transfer_target_resolver' => \stdClass::class]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a class that implements');

        TransferTargets::resolver();
    }
}
