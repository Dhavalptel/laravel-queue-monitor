<?php

namespace DhavalPtel\QueueMonitor\Collectors;

use DhavalPtel\QueueMonitor\Contracts\MetricsCollector;
use DhavalPtel\QueueMonitor\Contracts\StorageDriver;

class RunningJobCollector implements MetricsCollector
{
    public function __construct(
        protected StorageDriver $storage,
    ) {}

    /**
     * Collect currently running jobs.
     */
    public function collect(): array
    {
        return $this->storage->getRunningJobs();
    }
}
