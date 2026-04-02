<?php

namespace DhavalPtel\QueueMonitor\Tests\Helpers;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Mockery;

class EventFactory
{
    /**
     * Create a fake JobProcessing event.
     */
    public static function jobProcessing(
        string $jobClass = 'App\\Jobs\\TestJob',
        string $queue = 'default',
        string $jobId = 'test-job-1',
        int $attempts = 1,
        string $connection = 'redis',
    ): JobProcessing {
        $job = self::mockJob($jobClass, $queue, $jobId, $attempts);

        return new JobProcessing($connection, $job);
    }

    /**
     * Create a fake JobProcessed event.
     */
    public static function jobProcessed(
        string $jobClass = 'App\\Jobs\\TestJob',
        string $queue = 'default',
        string $jobId = 'test-job-1',
        int $attempts = 1,
        string $connection = 'redis',
    ): JobProcessed {
        $job = self::mockJob($jobClass, $queue, $jobId, $attempts);

        return new JobProcessed($connection, $job);
    }

    /**
     * Create a fake JobFailed event.
     */
    public static function jobFailed(
        string $jobClass = 'App\\Jobs\\TestJob',
        string $queue = 'default',
        string $jobId = 'test-job-1',
        int $attempts = 3,
        ?int $maxTries = 3,
        string $exceptionMessage = 'Something went wrong',
        string $connection = 'redis',
    ): JobFailed {
        $job = self::mockJob($jobClass, $queue, $jobId, $attempts, $maxTries);
        $exception = new \RuntimeException($exceptionMessage);

        return new JobFailed($connection, $job, $exception);
    }

    /**
     * Create a mock Job instance.
     */
    protected static function mockJob(
        string $jobClass,
        string $queue,
        string $jobId,
        int $attempts = 1,
        ?int $maxTries = null,
    ): Job {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('getJobId')->andReturn($jobId);
        $job->shouldReceive('resolveName')->andReturn($jobClass);
        $job->shouldReceive('getQueue')->andReturn($queue);
        $job->shouldReceive('attempts')->andReturn($attempts);
        $job->shouldReceive('maxTries')->andReturn($maxTries);
        $job->shouldReceive('payload')->andReturn([
            'uuid' => $jobId,
            'displayName' => $jobClass,
            'attempts' => $attempts,
            'maxTries' => $maxTries,
        ]);

        return $job;
    }
}

/**
 * Seed Redis with fake queue data for testing.
 */
function seedRedisQueues(array $queueSizes = []): void
{
    $redis = \Illuminate\Support\Facades\Redis::connection(
        config('queue-monitor.redis_connection', 'default')
    );

    foreach ($queueSizes as $queue => $size) {
        for ($i = 0; $i < $size; $i++) {
            $redis->rpush("queues:{$queue}", json_encode([
                'uuid' => "fake-{$queue}-{$i}",
                'displayName' => "App\\Jobs\\FakeJob",
                'attempts' => 0,
            ]));
        }
    }
}

/**
 * Create a test user with authorization.
 */
function authorizedUser(): \Illuminate\Foundation\Auth\User
{
    $user = new class extends \Illuminate\Foundation\Auth\User
    {
        public $email = 'admin@test.com';

        public function getAuthIdentifier()
        {
            return 1;
        }
    };

    \Illuminate\Support\Facades\Gate::define('viewQueueMonitor', fn () => true);

    return $user;
}

/**
 * Create a test user without authorization.
 */
function regularUser(): \Illuminate\Foundation\Auth\User
{
    $user = new class extends \Illuminate\Foundation\Auth\User
    {
        public $email = 'user@test.com';

        public function getAuthIdentifier()
        {
            return 2;
        }
    };

    \Illuminate\Support\Facades\Gate::define('viewQueueMonitor', fn () => false);

    return $user;
}
