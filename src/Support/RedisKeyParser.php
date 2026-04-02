<?php

namespace DhavalPtel\QueueMonitor\Support;

class RedisKeyParser
{
    /**
     * Parse a Redis queue key into its components.
     *
     * Laravel stores queues as:
     *   queues:{name}            → pending (LIST)
     *   queues:{name}:delayed    → delayed (SORTED SET)
     *   queues:{name}:reserved   → reserved (SORTED SET)
     */
    public function parse(string $key): ?ParsedKey
    {
        $prefix = config('queue-monitor.queue_prefix', '');

        // Strip the prefix if present
        if ($prefix && str_starts_with($key, $prefix)) {
            $key = substr($key, strlen($prefix));
        }

        if (! str_starts_with($key, 'queues:')) {
            return null;
        }

        $remainder = substr($key, 7); // Remove 'queues:'

        if (str_ends_with($remainder, ':delayed')) {
            return new ParsedKey(
                queue: substr($remainder, 0, -8),
                type: 'delayed'
            );
        }

        if (str_ends_with($remainder, ':reserved')) {
            return new ParsedKey(
                queue: substr($remainder, 0, -9),
                type: 'reserved'
            );
        }

        if (str_ends_with($remainder, ':notify')) {
            return null; // Skip notification channels
        }

        return new ParsedKey(
            queue: $remainder,
            type: 'pending'
        );
    }

    /**
     * Build a Redis key for a given queue and type.
     */
    public function buildKey(string $queue, string $type): string
    {
        $prefix = config('queue-monitor.queue_prefix', '');

        return match ($type) {
            'pending' => $prefix . 'queues:' . $queue,
            'delayed' => $prefix . 'queues:' . $queue . ':delayed',
            'reserved' => $prefix . 'queues:' . $queue . ':reserved',
            default => $prefix . 'queues:' . $queue,
        };
    }
}
