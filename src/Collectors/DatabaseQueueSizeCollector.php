<?php

namespace DhavalPtel\QueueMonitor\Collectors;

use Illuminate\Support\Facades\DB;

class DatabaseQueueSizeCollector
{
    /**
     * Collect queue sizes from the database jobs table.
     */
    public function collect(): array
    {
        $queues = config('queue-monitor.connections.database.queues', []);
        $table  = config('queue-monitor.connections.database.table', 'jobs');
        $now    = now()->timestamp;
        $result = [];

        foreach ($queues as $queue) {
            $pending  = DB::table($table)
                ->where('queue', $queue)
                ->whereNull('reserved_at')
                ->where('available_at', '<=', $now)
                ->count();

            $delayed  = DB::table($table)
                ->where('queue', $queue)
                ->where('available_at', '>', $now)
                ->count();

            $reserved = DB::table($table)
                ->where('queue', $queue)
                ->whereNotNull('reserved_at')
                ->count();

            $result[] = [
                'name'     => $queue,
                'driver'   => 'database',
                'pending'  => $pending,
                'delayed'  => $delayed,
                'reserved' => $reserved,
                'status'   => $this->determineStatus($pending),
            ];
        }

        return $result;
    }

    protected function determineStatus(int $pending): string
    {
        $threshold = config('queue-monitor.alerts.backlog_threshold', 500);

        if ($pending >= $threshold) {
            return 'critical';
        }

        if ($pending >= ($threshold * 0.5)) {
            return 'backlog';
        }

        return 'healthy';
    }
}
