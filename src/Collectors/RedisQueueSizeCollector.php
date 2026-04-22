<?php

namespace DhavalPtel\QueueMonitor\Collectors;

use DhavalPtel\QueueMonitor\Contracts\MetricsCollector;
use DhavalPtel\QueueMonitor\Support\RedisKeyParser;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

class RedisQueueSizeCollector implements MetricsCollector
{
    protected RedisKeyParser $parser;
    protected string $connection;

    public function __construct(RedisKeyParser $parser)
    {
        $this->parser = $parser;
        $this->connection = config('queue-monitor.redis_connection', 'default');
    }

    /**
     * Collect queue sizes for all monitored queues.
     */
    public function collect(): array
    {
        $queues = $this->getMonitoredQueues();
        $result = [];

        foreach ($queues as $queue) {
            $result[] = [
                'name' => $queue,
                'pending' => $this->getPendingCount($queue),
                'delayed' => $this->getDelayedCount($queue),
                'reserved' => $this->getReservedCount($queue),
                'status' => $this->determineStatus($queue),
            ];
        }

        return $result;
    }

    /**
     * Get the count of pending jobs in a queue.
     */
    public function getPendingCount(string $queue): int
    {
        return (int) $this->redis()->llen($this->parser->buildKey($queue, 'pending'));
    }

    /**
     * Get the count of delayed jobs in a queue.
     */
    public function getDelayedCount(string $queue): int
    {
        return (int) $this->redis()->zcard($this->parser->buildKey($queue, 'delayed'));
    }

    /**
     * Get the count of reserved (processing) jobs in a queue.
     */
    public function getReservedCount(string $queue): int
    {
        return (int) $this->redis()->zcard($this->parser->buildKey($queue, 'reserved'));
    }

    /**
     * Determine the health status of a queue.
     */
    protected function determineStatus(string $queue): string
    {
        $pending = $this->getPendingCount($queue);
        $backlogThreshold = config('queue-monitor.alerts.backlog_threshold', 500);

        if ($pending >= $backlogThreshold) {
            return 'critical';
        }

        if ($pending >= ($backlogThreshold * 0.5)) {
            return 'backlog';
        }

        return 'healthy';
    }

    /**
     * Get the list of queues to monitor.
     * Checks connections.redis.queues first, falls back to legacy 'queues' key.
     */
    protected function getMonitoredQueues(): array
    {
        $configured = config('queue-monitor.connections.redis.queues',
            config('queue-monitor.queues', ['*']));

        if ($configured === ['*']) {
            return $this->discoverQueues();
        }

        return $configured;
    }

    /**
     * Auto-discover queues by scanning Redis keys.
     */
    protected function discoverQueues(): array
    {
        $queues = [];
        $cursor = null;

        do {
            $result = $this->redis()->scan($cursor, [
                'match' => 'queues:*',
                'count' => 100,
            ]);

            if ($result === false) {
                break;
            }

            [$cursor, $keys] = $result;

            foreach ($keys as $key) {
                $parsed = $this->parser->parse($key);
                if ($parsed && ! in_array($parsed->queue, $queues)) {
                    $queues[] = $parsed->queue;
                }
            }
        } while ($cursor != 0);

        sort($queues);

        return $queues;
    }

    /**
     * Get the Redis connection.
     */
    protected function redis(): Connection
    {
        return Redis::connection($this->connection);
    }
}
