<?php

namespace DhavalPtel\QueueMonitor\Storage;

use DhavalPtel\QueueMonitor\Collectors\RedisQueueSizeCollector;
use DhavalPtel\QueueMonitor\Contracts\StorageDriver;

class MetricsAggregator
{
    public function __construct(
        protected StorageDriver $storage,
        protected RedisQueueSizeCollector $queueSizeCollector,
    ) {}

    /**
     * Get aggregate stats — delegates to storage.
     */
    public function stats(): array
    {
        return $this->storage->getStats();
    }

    /**
     * Get a complete snapshot of all metrics.
     */
    public function snapshot(): array
    {
        return [
            'queues' => $this->queueSizeCollector->collect(),
            'running' => $this->storage->getRunningJobs(),
            'failed' => $this->storage->getFailedJobs(),
            'throughput' => $this->storage->getThroughput(),
            'stats' => $this->storage->getStats(),
        ];
    }

    /**
     * Calculate throughput rate (jobs per minute) for recent window.
     */
    public function throughputRate(int $minutes = 5): float
    {
        $throughput = $this->storage->getThroughput($minutes);
        $total = array_sum(array_column($throughput, 'jobs'));

        return $minutes > 0 ? round($total / $minutes, 1) : 0;
    }

    /**
     * Calculate failure rate as a percentage.
     */
    public function failureRate(int $minutes = 60): float
    {
        $stats = $this->storage->getStats();

        return $stats['failure_rate'] ?? 0;
    }

    /**
     * Determine overall system health.
     */
    public function health(): string
    {
        $stats = $this->storage->getStats();
        $queues = $this->queueSizeCollector->collect();

        $maxPending = 0;
        foreach ($queues as $queue) {
            $maxPending = max($maxPending, $queue['pending']);
        }

        $backlogThreshold = config('queue-monitor.alerts.backlog_threshold', 500);
        $failureThreshold = config('queue-monitor.alerts.failure_rate_threshold', 5.0);

        if ($stats['failure_rate'] >= $failureThreshold) {
            return 'critical';
        }

        if ($maxPending >= $backlogThreshold) {
            return 'warning';
        }

        return 'healthy';
    }
}
