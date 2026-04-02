<?php

use DhavalPtel\QueueMonitor\Listeners\JobFailedListener;
use DhavalPtel\QueueMonitor\Listeners\JobProcessingListener;
use DhavalPtel\QueueMonitor\Storage\RedisStorage;
use DhavalPtel\QueueMonitor\Tests\Helpers\EventFactory;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->storage = app(RedisStorage::class);
    $this->processingListener = new JobProcessingListener($this->storage);
    $this->failedListener = new JobFailedListener($this->storage);
});

it('records a failure in the sorted set', function () {
    $event = EventFactory::jobFailed(
        jobClass: 'App\\Jobs\\ChargeSubscription',
        queue: 'high',
        jobId: 'job-fail-1',
        exceptionMessage: 'Card was declined',
    );

    $this->failedListener->handle($event);

    $entries = Redis::zrevrange('qm-test:failed', 0, -1);

    expect($entries)->toHaveCount(1);

    $data = json_decode($entries[0], true);
    expect($data)
        ->job->toBe('App\\Jobs\\ChargeSubscription')
        ->queue->toBe('high')
        ->exception->toBe('Card was declined');
});

it('removes the running entry on failure', function () {
    $processingEvent = EventFactory::jobProcessing(jobId: 'job-fail-run', queue: 'default');
    $this->processingListener->handle($processingEvent);

    expect(Redis::exists('qm-test:running:job-fail-run'))->toBe(1);

    $failedEvent = EventFactory::jobFailed(jobId: 'job-fail-run', queue: 'default');
    $this->failedListener->handle($failedEvent);

    expect(Redis::exists('qm-test:running:job-fail-run'))->toBe(0);
});

it('records attempts and max_tries', function () {
    $event = EventFactory::jobFailed(
        jobId: 'job-fail-attempts',
        attempts: 3,
        maxTries: 3,
    );

    $this->failedListener->handle($event);

    $entries = Redis::zrevrange('qm-test:failed', 0, -1);
    $data = json_decode($entries[0], true);

    expect($data)
        ->attempts->toBe(3)
        ->max_tries->toBe(3);
});

it('stores multiple failures in chronological order', function () {
    for ($i = 0; $i < 3; $i++) {
        $event = EventFactory::jobFailed(
            jobClass: "App\\Jobs\\FailJob{$i}",
            jobId: "job-order-{$i}",
        );
        $this->failedListener->handle($event);
        usleep(10000);
    }

    $entries = Redis::zrevrange('qm-test:failed', 0, -1);
    expect($entries)->toHaveCount(3);

    $first = json_decode($entries[0], true);
    $last = json_decode($entries[2], true);
    expect($first['failed_at'])->toBeGreaterThanOrEqual($last['failed_at']);
});

it('does nothing when monitoring is disabled', function () {
    config(['queue-monitor.enabled' => false]);

    $event = EventFactory::jobFailed(jobId: 'job-fail-disabled');
    $this->failedListener->handle($event);

    expect(Redis::zcard('qm-test:failed'))->toBe(0);

    config(['queue-monitor.enabled' => true]);
});
