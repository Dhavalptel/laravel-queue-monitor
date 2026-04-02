<?php

namespace DhavalPtel\QueueMonitor\Commands;

use DhavalPtel\QueueMonitor\QueueMonitor;
use Illuminate\Console\Command;

class PurgeCommand extends Command
{
    protected $signature = 'queue-monitor:purge
                            {--failed-only : Only purge failed job records}';

    protected $description = 'Purge queue monitoring data from Redis';

    public function handle(QueueMonitor $monitor): int
    {
        if ($this->option('failed-only')) {
            $monitor->purgeFailedOnly();
            $this->info('Failed job records purged.');
        } else {
            $monitor->purgeAll();
            $this->info('All monitoring data purged.');
        }

        return self::SUCCESS;
    }
}
