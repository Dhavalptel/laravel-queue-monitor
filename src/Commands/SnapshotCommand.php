<?php

namespace DhavalPtel\QueueMonitor\Commands;

use DhavalPtel\QueueMonitor\QueueMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SnapshotCommand extends Command
{
    protected $signature = 'queue-monitor:snapshot';

    protected $description = 'Take a point-in-time snapshot of queue metrics and store in the database';

    public function handle(QueueMonitor $monitor): int
    {
        if (! config('queue-monitor.snapshot.enabled', false)) {
            $this->warn('Snapshots are disabled. Enable them in config/queue-monitor.php');

            return self::FAILURE;
        }

        $stats = $monitor->stats();
        $queues = $monitor->queueSizes();

        DB::table('queue_monitor_snapshots')->insert([
            'processed_per_hour' => $stats['processed_per_hour'],
            'total_pending' => $stats['total_pending'],
            'running_count' => $stats['running_count'],
            'failed_count' => $stats['failed_count'],
            'failure_rate' => $stats['failure_rate'],
            'avg_wait_seconds' => $stats['avg_wait_seconds'],
            'queue_sizes' => json_encode($queues),
            'created_at' => now(),
        ]);

        $this->info('Snapshot taken at ' . now()->toDateTimeString());

        return self::SUCCESS;
    }
}
