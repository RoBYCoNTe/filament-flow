<?php

namespace RoBYCoNTe\FilamentFlow\Commands;

use Illuminate\Console\Command;
use RoBYCoNTe\FilamentFlow\Services\ScheduledCheckRunner;
use Throwable;

class ProcessScheduledChecksCommand extends Command
{
    protected $signature = 'workflow:process-schedules';

    protected $description = 'Process all active workflow scheduled checks';

    public function handle(ScheduledCheckRunner $runner): int
    {
        $this->info('Processing workflow scheduled checks...');

        try {
            $results = $runner->runAll();

            $this->info("Processed: {$results['processed']} records");
            $this->info("Triggered: {$results['triggered']} actions");

            if ($results['errors'] > 0) {
                $this->warn("Errors: {$results['errors']}");
            }
        } catch (Throwable $e) {
            $this->error("Failed: {$e->getMessage()}");
            report($e);

            return self::FAILURE;
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
