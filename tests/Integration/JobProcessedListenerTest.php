<?php

use DhavalPtel\QueueMonitor\Listeners\JobProcessedListener;
use DhavalPtel\QueueMonitor\Listeners\JobProcessingListener;
use DhavalPtel\QueueMonitor\Storage\RedisStorage;
use DhavalPtel\QueueMonitor\Tests\Helpers\EventFactory;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->storage = app(RedisStorage::class);
    $this->processingListener = new JobProcessingListener($this->storage);
    $this->processedListener = new JobProcessedListener($this->storage);
});

it('removes the running entry when a job completes', function () {
    $processingEvent = EventFactory::jobProcessing(jobId: 'job-complete-1', queue: 'default');
    $this->processingListener->handle($processingEvent);

    expect(Redis::exists('qm-test:running:job-complete-1'))->toBe(1);

    $processedEvent = EventFactory::jobProcessed(jobId: 'job-complete-1', queue: 'default');
    $this->processedListener->handle($processedEvent);

    expect(Redis::exists('qm-test:running:job-complete-1'))->toBe(0);
});

it('increments the throughput counter on completion', function () {
    $event = EventFactory::jobProcessed(queue: 'high', jobId: 'job-tp-1');
    $this->processedListener->handle($event);

    $bucket = $this->storage->currentMinuteBucket();
    $count = (int) Redis::get("qm-test:throughput:high:{$bucket}");

    expect($count)->toBe(1);
});

it('increments throughput counter cumulatively', function () {
    for ($i = 0; $i < 5; $i++) {
        $event = EventFactory::jobProcessed(queue: 'default', jobId: "job-tp-cum-{$i}");
        $this->processedListener->handle($event);
    }

    $bucket = $this->storage->currentMinuteBucket();
    $count = (int) Redis::get("qm-test:throughput:default:{$bucket}");

    expect($count)->toBe(5);
});

it('sets TTL on throughput keys', function () {
    $event = EventFactory::jobProcessed(queue: 'emails', jobId: 'job-tp-ttl');
    $this->processedListener->handle($event);

    $bucket = $this->storage->currentMinuteBucket();
    $ttl = Redis::ttl("qm-test:throughput:emails:{$bucket}");

    expect($ttl)->toBeGreaterThan(3500)->toBeLessThanOrEqual(3660);
});

it('does nothing when monitoring is disabled', function () {
    config(['queue-monitor.enabled' => false]);

    $event = EventFactory::jobProcessed(queue: 'default', jobId: 'job-disabled');
    $this->processedListener->handle($event);

    $bucket = $this->storage->currentMinuteBucket();
    expect(Redis::exists("qm-test:throughput:default:{$bucket}"))->toBe(0);

    config(['queue-monitor.enabled' => true]);
});
