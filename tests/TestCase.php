<?php

namespace DhavalPtel\QueueMonitor\Tests;

use DhavalPtel\QueueMonitor\QueueMonitorServiceProvider;
use Illuminate\Support\Facades\Redis;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->flushMonitoringKeys();
    }

    protected function tearDown(): void
    {
        $this->flushMonitoringKeys();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            QueueMonitorServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'QueueMonitor' => \DhavalPtel\QueueMonitor\Facades\QueueMonitor::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.redis.client', 'phpredis');
        $app['config']->set('database.redis.default', [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_TEST_DB', 15),
        ]);
        $app['config']->set('queue.default', 'redis');
        $app['config']->set('queue.connections.redis.connection', 'default');

        $app['config']->set('queue-monitor.enabled', true);
        $app['config']->set('queue-monitor.redis_connection', 'default');
        $app['config']->set('queue-monitor.prefix', 'qm-test');
        $app['config']->set('queue-monitor.queues', ['default', 'high', 'emails']);
        $app['config']->set('queue-monitor.retention.running_job_ttl', 7200);
        $app['config']->set('queue-monitor.retention.throughput_minutes', 60);
        $app['config']->set('queue-monitor.retention.failed_max_entries', 100);
        $app['config']->set('queue-monitor.dashboard.enabled', true);
        $app['config']->set('queue-monitor.dashboard.path', 'queue-monitor');
        $app['config']->set('queue-monitor.dashboard.middleware', ['web']);
    }

    protected function flushMonitoringKeys(): void
    {
        try {
            $keys = Redis::keys('qm-test:*');
            if (! empty($keys)) {
                Redis::del(...$keys);
            }
        } catch (\Exception $e) {
            // Redis not available, skip
        }
    }
}
