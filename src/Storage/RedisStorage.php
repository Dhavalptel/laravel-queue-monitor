<?php

namespace DhavalPtel\QueueMonitor\Storage;

use DhavalPtel\QueueMonitor\Contracts\StorageDriver;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

class RedisStorage implements StorageDriver
{
    protected string $prefix;
    protected string $connection;

    public function __construct()
    {
        $this->prefix = config('queue-monitor.prefix', 'queue-monitor');
        $this->connection = config('queue-monitor.redis_connection', 'default');
    }

    /**
     * Record a job as running.
     */
    public function markJobRunning(string $jobId, array $data): void
    {
        $key = $this->key("running:{$jobId}");
        $ttl = config('queue-monitor.retention.running_job_ttl', 7200);

        $this->redis()->hmset($key, [
            'job' => $data['job'],
            'queue' => $data['queue'],
            'started_at' => $data['started_at'] ?? Carbon::now()->timestamp,
            'attempt' => $data['attempt'] ?? 1,
        ]);

        $this->redis()->expire($key, $ttl);
    }

    /**
     * Remove a job from the running list and record completion.
     */
    public function markJobCompleted(string $jobId, string $queue): void
    {
        // Remove from running
        $this->redis()->del($this->key("running:{$jobId}"));

        // Increment throughput counter for this minute bucket
        $bucket = Carbon::now()->format('YmdHi');
        $throughputKey = $this->key("throughput:{$queue}:{$bucket}");
        $ttl = (config('queue-monitor.retention.throughput_minutes', 60) + 1) * 60;

        $this->redis()->incr($throughputKey);
        $this->redis()->expire($throughputKey, $ttl);

        // Increment total processed counter
        $totalKey = $this->key("stats:total_processed:{$queue}");
        $this->redis()->incr($totalKey);
        $this->redis()->expire($totalKey, $ttl);
    }

    /**
     * Record a job failure.
     */
    public function markJobFailed(string $jobId, array $data): void
    {
        // Remove from running
        $this->redis()->del($this->key("running:{$jobId}"));

        // Add to failed sorted set (scored by timestamp)
        $timestamp = Carbon::now()->timestamp;
        $failedKey = $this->key('failed');
        $maxEntries = config('queue-monitor.retention.failed_max_entries', 1000);

        $entry = json_encode([
            'id' => $jobId,
            'job' => $data['job'],
            'queue' => $data['queue'],
            'exception' => $this->truncateException($data['exception'] ?? ''),
            'failed_at' => Carbon::now()->timestamp,
            'attempts' => $data['attempts'] ?? 0,
            'max_tries' => $data['max_tries'] ?? null,
        ]);

        $this->redis()->zadd($failedKey, $timestamp, $entry);

        // Trim to max entries (remove oldest)
        $count = $this->redis()->zcard($failedKey);
        if ($count > $maxEntries) {
            $this->redis()->zremrangebyrank($failedKey, 0, $count - $maxEntries - 1);
        }

        // Increment failure counter for this minute
        $bucket = Carbon::now()->format('YmdHi');
        $failureCountKey = $this->key("stats:failed:{$data['queue']}:{$bucket}");
        $ttl = (config('queue-monitor.retention.throughput_minutes', 60) + 1) * 60;

        $this->redis()->incr($failureCountKey);
        $this->redis()->expire($failureCountKey, $ttl);
    }

    /**
     * Get all currently running jobs.
     */
    public function getRunningJobs(): array
    {
        $pattern = $this->key('running:*');
        $keys = $this->scanKeys($pattern);
        $jobs = [];

        foreach ($keys as $key) {
            $data = $this->redis()->hgetall($key);
            if (empty($data)) {
                continue;
            }

            $jobId = str_replace($this->key('running:'), '', $key);
            $startedAt = (int) ($data['started_at'] ?? 0);

            $jobs[] = [
                'id' => $jobId,
                'job' => $data['job'] ?? 'Unknown',
                'queue' => $data['queue'] ?? 'default',
                'started_at' => Carbon::createFromTimestamp($startedAt)->toIso8601String(),
                'duration_seconds' => round(Carbon::now()->timestamp - $startedAt, 1),
                'attempt' => (int) ($data['attempt'] ?? 1),
            ];
        }

        // Sort by duration descending (longest running first)
        usort($jobs, fn ($a, $b) => $b['duration_seconds'] <=> $a['duration_seconds']);

        return $jobs;
    }

    /**
     * Get recent failed jobs.
     */
    public function getFailedJobs(int $limit = 50): array
    {
        $failedKey = $this->key('failed');

        // Get the most recent entries (highest score = most recent)
        $entries = $this->redis()->zrevrange($failedKey, 0, $limit - 1);
        $jobs = [];

        foreach ($entries as $entry) {
            $data = json_decode($entry, true);
            if ($data) {
                $jobs[] = $data;
            }
        }

        return $jobs;
    }

    /**
     * Get throughput data (jobs per minute).
     */
    public function getThroughput(int $minutes = 60): array
    {
        $result = [];
        $now = Carbon::now();

        for ($i = $minutes - 1; $i >= 0; $i--) {
            $time = $now->copy()->subMinutes($i);
            $bucket = $time->format('YmdHi');
            $total = 0;

            // Sum across all queues for this bucket
            $pattern = $this->key("throughput:*:{$bucket}");
            $keys = $this->scanKeys($pattern);

            foreach ($keys as $key) {
                $total += (int) $this->redis()->get($key);
            }

            $result[] = [
                'time' => $time->format('H:i'),
                'timestamp' => $time->timestamp,
                'jobs' => $total,
            ];
        }

        return $result;
    }

    /**
     * Get aggregate statistics.
     */
    public function getStats(): array
    {
        $throughput = $this->getThroughput(60);
        $totalProcessed = array_sum(array_column($throughput, 'jobs'));
        $runningJobs = $this->getRunningJobs();
        $failedJobs = $this->getFailedJobs(limit: 100);

        // Count failures in the last hour
        $oneHourAgo = Carbon::now()->subHour()->timestamp;
        $recentFailures = count(array_filter($failedJobs, function ($job) use ($oneHourAgo) {
            return ($job['failed_at'] ?? 0) >= $oneHourAgo;
        }));

        $failureRate = $totalProcessed > 0
            ? round(($recentFailures / ($totalProcessed + $recentFailures)) * 100, 2)
            : 0;

        // Calculate average wait time from running jobs
        $avgDuration = count($runningJobs) > 0
            ? round(array_sum(array_column($runningJobs, 'duration_seconds')) / count($runningJobs), 1)
            : 0;

        return [
            'processed_per_hour'  => $totalProcessed,
            'running_count'       => count($runningJobs),
            'failed_count'        => $recentFailures,
            'failed_last_hour'    => $recentFailures,
            'failure_rate'        => $failureRate,
            'avg_wait_seconds'    => $avgDuration,
            'avg_processing_seconds' => $avgDuration,
            'total_pending'       => 0, // populated by queue size collector
        ];
    }

    /**
     * Purge all monitoring data.
     */
    public function purgeAll(): void
    {
        $keys = $this->scanKeys($this->key('*'));

        if (! empty($keys)) {
            $this->redis()->del(...$keys);
        }
    }

    /**
     * Purge only failed jobs data.
     */
    public function purgeFailed(): void
    {
        $this->redis()->del($this->key('failed'));

        // Also purge failure count stats
        $keys = $this->scanKeys($this->key('stats:failed:*'));
        if (! empty($keys)) {
            $this->redis()->del(...$keys);
        }
    }

    /**
     * Build a prefixed key.
     */
    public function key(string $suffix): string
    {
        return $this->prefix . ':' . $suffix;
    }

    /**
     * Scan Redis keys matching a pattern.
     */
    protected function scanKeys(string $pattern): array
    {
        $keys = [];
        $cursor = null;

        do {
            $result = $this->redis()->scan($cursor, [
                'match' => $pattern,
                'count' => 100,
            ]);

            if ($result === false) {
                break;
            }

            [$cursor, $found] = $result;
            $keys = array_merge($keys, $found);
        } while ($cursor != 0);

        return $keys;
    }

    /**
     * Truncate exception messages to a reasonable length.
     */
    protected function truncateException(string $exception): string
    {
        $maxLength = 500;

        if (strlen($exception) <= $maxLength) {
            return $exception;
        }

        return substr($exception, 0, $maxLength) . '...';
    }

    /**
     * Get the Redis connection.
     */
    protected function redis(): Connection
    {
        return Redis::connection($this->connection);
    }
}
