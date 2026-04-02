<?php

namespace DhavalPtel\QueueMonitor;

use DhavalPtel\QueueMonitor\Collectors\FailedJobCollector;
use DhavalPtel\QueueMonitor\Collectors\RedisQueueSizeCollector;
use DhavalPtel\QueueMonitor\Collectors\RunningJobCollector;
use DhavalPtel\QueueMonitor\Storage\MetricsAggregator;
use DhavalPtel\QueueMonitor\Storage\RedisStorage;

class QueueMonitor
{
    public function __construct(
        protected RedisStorage $storage,
        protected RedisQueueSizeCollector $queueSizeCollector,
        protected RunningJobCollector $runningJobCollector,
        protected FailedJobCollector $failedJobCollector,
        protected MetricsAggregator $aggregator,
    ) {}

    /**
     * Get sizes for all monitored queues.
     */
    public function queueSizes(): array
    {
        return $this->queueSizeCollector->collect();
    }

    /**
     * Get all currently running jobs.
     */
    public function runningJobs(): array
    {
        return $this->runningJobCollector->collect();
    }

    /**
     * Get recent failed jobs.
     */
    public function failedJobs(int $limit = 50): array
    {
        return $this->storage->getFailedJobs($limit);
    }

    /**
     * Get throughput data for a specific queue.
     */
    public function throughput(string $queue = 'default', int $minutes = 60): array
    {
        return $this->storage->getThroughput($queue, $minutes);
    }

    /**
     * Get throughput data for all monitored queues.
     */
    public function allThroughput(int $minutes = 60): array
    {
        $queues = $this->queueSizeCollector->collect();
        $result = [];

        foreach ($queues as $queue) {
            $result[$queue['name']] = $this->storage->getThroughput($queue['name'], $minutes);
        }

        return $result;
    }

    /**
     * Get aggregate stats across all queues.
     */
    public function stats(): array
    {
        return $this->aggregator->stats();
    }

    /**
     * Purge all monitoring data.
     */
    public function purgeAll(): void
    {
        $this->storage->purgeAll();
    }

    /**
     * Purge only failed job records.
     */
    public function purgeFailedOnly(): void
    {
        $this->storage->purgeFailedOnly();
    }

    /**
     * Check if monitoring is enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) config('queue-monitor.enabled', true);
    }
}
