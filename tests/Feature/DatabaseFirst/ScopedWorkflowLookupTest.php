<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoBYCoNTe\FilamentFlow\Services\StateService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\ScopedOrder;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * With one workflow per owner, every read has to say **which**: without the tenant one reads
 * the workflow of somebody else, and the answer is wrong in silence — no error, only an initial
 * state or a permission that belongs to another part of the system.
 */
class ScopedWorkflowLookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('test_scoped_orders')) {
            Schema::create('test_scoped_orders', function (Blueprint $table) {
                $table->id();
                $table->string('state')->default('draft');
                $table->unsignedBigInteger('scope_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_the_initial_state_comes_from_the_workflow_of_the_given_scope(): void
    {
        $this->scopedWorkflow(1, 'draft');
        $this->scopedWorkflow(2, 'received');

        $states = app(StateService::class);

        $this->assertSame('draft', $states->getInitialState(ScopedOrder::class, 'state', 1));
        $this->assertSame('received', $states->getInitialState(ScopedOrder::class, 'state', 2));
    }

    /**
     * Without naming the owner **nothing is found**: no error, no exception, `null`. It is the
     * silence that had us chase an empty dropdown, an empty badge and a dialog without fields
     * for a whole day: the lookup asks for `tenant_id = null`, does not find the workflow of
     * the call, and the caller receives `null` without knowing why.
     */
    public function test_without_a_scope_the_lookup_finds_nothing(): void
    {
        $this->scopedWorkflow(1, 'draft');
        $this->scopedWorkflow(2, 'received');

        $this->assertNull(
            app(StateService::class)->getInitialState(ScopedOrder::class),
            'Senza tenant la ricerca non trova il workflow, e tace.'
        );
    }

    private function scopedWorkflow(int $scopeId, string $initialState): void
    {
        $workflow = $this->createTestWorkflow([
            'name' => "scope-{$scopeId}-lookup",
            'model_type' => ScopedOrder::class,
            'state_column' => 'state',
            'tenant_id' => $scopeId,
        ]);

        $this->createWorkflowState($workflow, [
            'name' => $initialState,
            'label' => ucfirst($initialState),
            'is_initial' => true,
        ]);
    }
}
