<?php

namespace DhavalPtel\QueueMonitor\Collectors;

use DhavalPtel\QueueMonitor\Contracts\MetricsCollector;

class UnifiedQueueSizeCollector implements MetricsCollector
{
    protected array $driverStatuses = [];

    public function __construct(
        protected RedisQueueSizeCollector    $redis,
        protected DatabaseQueueSizeCollector $database,
    ) {}

    /**
     * Collect queue sizes from all configured drivers.
     * Each entry includes a 'driver' field ('redis' or 'database').
     */
    public function collect(): array
    {
        $result = [];

        // Redis queues — fall back to legacy 'queues' key for backward compat
        $redisQueues = config('queue-monitor.connections.redis.queues',
            config('queue-monitor.queues', []));

        if (! empty($redisQueues)) {
            try {
                foreach ($this->redis->collect() as $queue) {
                    $queue['driver'] = 'redis';
                    $result[] = $queue;
                }
                $this->driverStatuses['redis'] = true;
            } catch (\Throwable) {
                $this->driverStatuses['redis'] = false;
            }
        }

        // Database queues
        $dbQueues = config('queue-monitor.connections.database.queues', []);

        if (! empty($dbQueues)) {
            try {
                foreach ($this->database->collect() as $queue) {
                    $result[] = $queue;
                }
                $this->driverStatuses['database'] = true;
            } catch (\Throwable) {
                $this->driverStatuses['database'] = false;
            }
        }

        return $result;
    }

    /**
     * Returns per-driver connectivity status after collect() has been called.
     * ['redis' => true, 'database' => false]
     */
    public function getDriverStatuses(): array
    {
        return $this->driverStatuses;
    }
}
