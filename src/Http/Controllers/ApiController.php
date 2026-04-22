<?php

namespace DhavalPtel\QueueMonitor\Http\Controllers;

use DhavalPtel\QueueMonitor\QueueMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ApiController extends Controller
{
    public function __construct(
        protected QueueMonitor $monitor,
    ) {}

    /**
     * GET /api/queues
     *
     * Returns all monitored queues with their pending, delayed, reserved counts.
     */
    public function queues(): JsonResponse
    {
        return response()->json([
            'queues' => $this->monitor->queueSizes(),
        ]);
    }

    /**
     * GET /api/running
     *
     * Returns all currently processing jobs.
     */
    public function running(): JsonResponse
    {
        $jobs = $this->monitor->runningJobs();

        return response()->json([
            'jobs' => $jobs,
            'total' => count($jobs),
        ]);
    }

    /**
     * GET /api/failed
     *
     * Returns recent failed jobs.
     */
    public function failed(Request $request): JsonResponse
    {
        $limit = min((int) $request->query('limit', 50), 1000);
        $jobs = $this->monitor->failedJobs($limit);

        return response()->json([
            'jobs' => $jobs,
            'total' => count($jobs),
        ]);
    }

    /**
     * GET /api/throughput
     *
     * Returns throughput data (jobs/min) for all queues.
     */
    public function throughput(Request $request): JsonResponse
    {
        $minutes = min((int) $request->query('minutes', 60), 1440);

        return response()->json([
            'queues' => $this->monitor->allThroughput($minutes),
            'minutes' => $minutes,
        ]);
    }

    /**
     * GET /api/stats
     *
     * Returns aggregate statistics.
     */
    public function stats(): JsonResponse
    {
        return response()->json($this->monitor->stats());
    }

    /**
     * GET /api/health
     *
     * Returns per-driver connectivity status.
     */
    public function health(): JsonResponse
    {
        $statuses = [];

        $redisQueues = config('queue-monitor.connections.redis.queues',
            config('queue-monitor.queues', []));

        if (! empty($redisQueues)) {
            try {
                $raw = Redis::connection(config('queue-monitor.redis_connection', 'default'))->ping();
                $statuses['redis'] = (is_object($raw) ? (string) $raw : $raw) === 'PONG';
            } catch (\Throwable) {
                $statuses['redis'] = false;
            }
        }

        $dbQueues = config('queue-monitor.connections.database.queues', []);

        if (! empty($dbQueues)) {
            try {
                DB::table(config('queue-monitor.connections.database.table', 'jobs'))->limit(1)->exists();
                $statuses['database'] = true;
            } catch (\Throwable) {
                $statuses['database'] = false;
            }
        }

        return response()->json($statuses);
    }
}
