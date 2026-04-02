<?php

use DhavalPtel\QueueMonitor\Listeners\JobProcessingListener;
use DhavalPtel\QueueMonitor\Storage\RedisStorage;
use DhavalPtel\QueueMonitor\Tests\Helpers\EventFactory;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->storage = app(RedisStorage::class);
    $this->listener = new JobProcessingListener($this->storage);
});

it('creates a running entry when a job starts processing', function () {
    $event = EventFactory::jobProcessing(
        jobClass: 'App\\Jobs\\ProcessPayment',
        queue: 'high',
        jobId: 'job-001',
    );

    $this->listener->handle($event);

    $data = Redis::hgetall('qm-test:running:job-001');

    expect($data)
        ->toHaveKey('job', 'App\\Jobs\\ProcessPayment')
        ->toHaveKey('queue', 'high');
});

it('sets a TTL on the running entry', function () {
    $event = EventFactory::jobProcessing(jobId: 'job-ttl-test');

    $this->listener->handle($event);

    $ttl = Redis::ttl('qm-test:running:job-ttl-test');

    expect($ttl)
        ->toBeGreaterThan(7000)
        ->toBeLessThanOrEqual(7200);
});

it('records the attempt number', function () {
    $event = EventFactory::jobProcessing(jobId: 'job-attempt', attempts: 2);

    $this->listener->handle($event);

    $data = Redis::hgetall('qm-test:running:job-attempt');

    expect($data['attempt'])->toBe('2');
});

it('does nothing when monitoring is disabled', function () {
    config(['queue-monitor.enabled' => false]);

    $event = EventFactory::jobProcessing(jobId: 'job-disabled');
    $this->listener->handle($event);

    expect(Redis::exists('qm-test:running:job-disabled'))->toBe(0);

    config(['queue-monitor.enabled' => true]);
});

it('handles jobs with empty IDs gracefully', function () {
    $event = EventFactory::jobProcessing(jobId: '');
    $this->listener->handle($event);

    // Should not throw, should not create an entry
    $keys = Redis::keys('qm-test:running:*');
    expect($keys)->toBeEmpty();
});
