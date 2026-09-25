<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Infolists;

use Illuminate\Support\Facades\Lang;
use RoBYCoNTe\FilamentFlow\Infolists\Components\AssignmentSummaryEntry;
use RoBYCoNTe\FilamentFlow\Models\WorkflowAssignment;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The summary of who holds a record: the people, the words their roles read in, when they were
 * given the case and by whose hand — and the state those permissions belong to.
 */
class AssignmentSummaryEntryTest extends TestCase
{
    public function test_the_entry_carries_a_translated_label(): void
    {
        $this->assertSame(
            __('filament-flow::messages.assignment_summary_label'),
            AssignmentSummaryEntry::make()->getLabel(),
        );
    }

    public function test_a_role_reads_in_the_words_the_host_gives_it(): void
    {
        $mapped = AssignmentSummaryEntry::make()->roleLabels(['super_admin' => 'Super Amministratore']);
        $this->assertSame('Super Amministratore', $mapped->getRoleLabel('super_admin'));

        $callback = AssignmentSummaryEntry::make()
            ->roleLabels(fn (string $role): ?string => $role === 'grant_operator' ? 'Redattore Atti di Concessione' : null);
        $this->assertSame('Redattore Atti di Concessione', $callback->getRoleLabel('grant_operator'));

        // A role the host does not know, and whose words are not written down anywhere, is
        // read as its name says — never as the raw key.
        $this->assertSame('Senior Collaborator', $callback->getRoleLabel('senior_collaborator'));
    }

    public function test_a_role_name_is_translated_when_the_words_are_known(): void
    {
        $directory = sys_get_temp_dir().'/filament-flow-lang-'.uniqid();
        mkdir($directory);
        file_put_contents($directory.'/en.json', json_encode([
            'senior_collaborator' => 'Collaboratore Senior',
            'reviewer' => 'Revisore',
        ]));

        Lang::addJsonPath($directory);

        $entry = AssignmentSummaryEntry::make();

        // The name itself, and the same name headlined, are both asked of the translations.
        $this->assertSame('Collaboratore Senior', $entry->getRoleLabel('senior_collaborator'));
        $this->assertSame('Revisore', $entry->getRoleLabel('reviewer'));

        unlink($directory.'/en.json');
        rmdir($directory);
    }

    public function test_the_assigned_people_carry_their_roles_their_date_and_their_author(): void
    {
        $author = $this->createTestUser(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
        $watcher = $this->createTestUser(['name' => 'Anna Bianchi', 'email' => 'anna@example.com']);
        $holder = $this->createTestUser(['name' => 'Giulia Verdi', 'email' => 'giulia@example.com']);

        $order = $this->createOrder();

        // Saved out of order on purpose: the summary reads the holder first.
        foreach ([[$watcher, 'viewer'], [$holder, 'primary']] as [$user, $type]) {
            WorkflowAssignment::create([
                'assignable_type' => Order::class,
                'assignable_id' => $order->id,
                'user_id' => $user->id,
                'assignment_type' => $type,
                'assigned_by' => $author->id,
                'assigned_at' => now()->subDay(),
                'override_view' => true,
            ]);
        }

        $assigned = AssignmentSummaryEntry::make()
            ->roleLabels(['super_admin' => 'Super Amministratore'])
            ->model($order)
            ->getAssignedUsersWithPermissions();

        $this->assertCount(2, $assigned);
        $this->assertSame('Giulia Verdi', $assigned[0]['user']->name);
        $this->assertSame('primary', $assigned[0]['assignment_type']);
        $this->assertSame('Anna Bianchi', $assigned[1]['user']->name);

        $this->assertSame('Mario Rossi', $assigned[0]['assigned_by']);
        $this->assertNotNull($assigned[0]['assigned_at']);
        $this->assertTrue($assigned[0]['has_overrides']);
        $this->assertTrue($assigned[0]['override_view']);
    }

    public function test_the_state_the_permissions_are_read_in_is_named(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'In istruttoria',
            'sort_order' => 0,
        ]);

        $order = $this->createOrder(['state' => 'processing']);

        $entry = AssignmentSummaryEntry::make()->model($order);

        $this->assertSame('In istruttoria', $entry->getStateLabel());

        // A record whose state is not one of the workflow's says nothing rather than guessing.
        $other = $this->createOrder(['state' => 'unknown']);

        $this->assertNull(AssignmentSummaryEntry::make()->model($other)->getStateLabel());
    }

    /** @param array<string,mixed> $data */
    private function createOrder(array $data = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-SUMMARY-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $data));
    }
}
