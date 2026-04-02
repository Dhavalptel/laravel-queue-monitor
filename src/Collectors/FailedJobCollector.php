<?php

namespace DhavalPtel\QueueMonitor\Collectors;

use DhavalPtel\QueueMonitor\Contracts\MetricsCollector;
use DhavalPtel\QueueMonitor\Storage\RedisStorage;

class FailedJobCollector implements MetricsCollector
{
    public function __construct(
        protected RedisStorage $storage,
    ) {}

    /**
     * Collect recent failed jobs.
     */
    public function collect(): array
    {
        $limit = config('queue-monitor.retention.failed_max_entries', 1000);

        return $this->storage->getFailedJobs($limit);
    }
}
