<?php

namespace DhavalPtel\QueueMonitor\Contracts;

interface StorageDriver
{
    /**
     * Record a job as running.
     */
    public function markJobRunning(string $jobId, array $data): void;

    /**
     * Remove a job from the running list and record completion.
     */
    public function markJobCompleted(string $jobId, string $queue): void;

    /**
     * Record a job failure.
     */
    public function markJobFailed(string $jobId, array $data): void;

    /**
     * Get all currently running jobs.
     */
    public function getRunningJobs(): array;

    /**
     * Get recent failed jobs.
     */
    public function getFailedJobs(int $limit = 50): array;

    /**
     * Get throughput data (jobs per minute).
     */
    public function getThroughput(int $minutes = 60): array;

    /**
     * Get aggregate statistics.
     */
    public function getStats(): array;

    /**
     * Purge all monitoring data.
     */
    public function purgeAll(): void;

    /**
     * Purge only failed jobs data.
     */
    public function purgeFailed(): void;
}
