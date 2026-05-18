<?php

namespace RoBYCoNTe\FilamentFlow\Commands;

use Illuminate\Console\Command;
use RoBYCoNTe\FilamentFlow\Models\Workflow;

class ListWorkflowsCommand extends Command
{
    protected $signature = 'filament-flow:list';

    protected $description = 'List all registered workflows';

    public function handle(): int
    {
        $workflows = Workflow::withCount(['states', 'transitions'])->get();

        if ($workflows->isEmpty()) {
            $this->info('No workflows found.');

            return self::SUCCESS;
        }

        $tenantForeignKey = config('filament-flow.tenant_foreign_key', 'tenant_id');

        $rows = $workflows->map(function (Workflow $workflow) use ($tenantForeignKey) {
            return [
                $workflow->id,
                $workflow->name,
                $workflow->model_type,
                $workflow->{$tenantForeignKey} ?? '-',
                $workflow->is_active ? 'Yes' : 'No',
                $workflow->states_count,
                $workflow->transitions_count,
            ];
        });

        $this->table(
            ['ID', 'Name', 'Model Class', 'Tenant ID', 'Active', 'States', 'Transitions'],
            $rows,
        );

        return self::SUCCESS;
    }
}
