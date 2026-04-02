<?php

namespace DhavalPtel\QueueMonitor\Commands;

use DhavalPtel\QueueMonitor\QueueMonitor;
use Illuminate\Console\Command;

class StatsCommand extends Command
{
    protected $signature = 'queue-monitor:stats';

    protected $description = 'Display current queue monitoring stats in the terminal';

    public function handle(QueueMonitor $monitor): int
    {
        if (! $monitor->isEnabled()) {
            $this->warn('Queue monitoring is disabled.');

            return self::FAILURE;
        }

        $this->info('Queue Monitor — ' . now()->toDateTimeString());
        $this->newLine();

        // Queue sizes table
        $queues = $monitor->queueSizes();

        if (empty($queues)) {
            $this->warn('No queues found. Are workers running with Redis?');

            return self::SUCCESS;
        }

        $this->table(
            ['Queue', 'Pending', 'Delayed', 'Reserved', 'Status'],
            array_map(fn ($q) => [
                $q['name'],
                $q['pending'],
                $q['delayed'],
                $q['reserved'],
                $this->formatStatus($q['status']),
            ], $queues),
        );

        // Summary stats
        $stats = $monitor->stats();
        $running = $monitor->runningJobs();

        $this->newLine();
        $this->line(sprintf(
            'Running: <info>%d</info> jobs | Failed (1hr): <error>%d</error> | Throughput: <info>%d</info> jobs/hr | Failure rate: %s%%',
            count($running),
            $stats['failed_count'],
            $stats['processed_per_hour'],
            $stats['failure_rate'],
        ));

        $this->line(sprintf(
            'Avg wait time: <info>%ss</info>',
            $stats['avg_wait_seconds'],
        ));

        return self::SUCCESS;
    }

    protected function formatStatus(string $status): string
    {
        return match ($status) {
            'healthy' => '<info>healthy</info>',
            'backlog' => '<comment>backlog</comment>',
            'critical' => '<error>critical</error>',
            default => $status,
        };
    }
}
