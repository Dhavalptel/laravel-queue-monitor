<?php

use DhavalPtel\QueueMonitor\Storage\RedisStorage;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->storage = app(RedisStorage::class);
});

it('running job keys have correct TTL', function () {
    $this->storage->markJobRunning('ttl-test-1', [
        'job' => 'App\\Jobs\\TestJob',
        'queue' => 'default',
        'started_at' => now()->timestamp,
        'attempt' => 1,
    ]);

    $ttl = Redis::ttl('qm-test:running:ttl-test-1');

    expect($ttl)
        ->toBeGreaterThan(7100)
        ->toBeLessThanOrEqual(7200);
});

it('throughput keys have correct TTL', function () {
    $this->storage->incrementThroughput('default');

    $bucket = $this->storage->currentMinuteBucket();
    $ttl = Redis::ttl("qm-test:throughput:default:{$bucket}");

    // Should be (throughput_minutes + 1) * 60 = 61 * 60 = 3660
    expect($ttl)
        ->toBeGreaterThan(3500)
        ->toBeLessThanOrEqual(3660);
});

it('purgeAll removes all monitoring keys', function () {
    $this->storage->markJobRunning('purge-1', [
        'job' => 'Test', 'queue' => 'default', 'started_at' => now()->timestamp, 'attempt' => 1,
    ]);
    $this->storage->incrementThroughput('default');
    $this->storage->recordFailure([
        'job' => 'Test', 'queue' => 'default', 'exception' => 'Error', 'failed_at' => now()->timestamp,
    ]);

    expect(Redis::keys('qm-test:*'))->not->toBeEmpty();

    $this->storage->purgeAll();

    expect(Redis::keys('qm-test:*'))->toBeEmpty();
});

it('purgeFailedOnly removes only failed job records', function () {
    $this->storage->markJobRunning('purge-selective', [
        'job' => 'Test', 'queue' => 'default', 'started_at' => now()->timestamp, 'attempt' => 1,
    ]);
    $this->storage->incrementThroughput('default');
    $this->storage->recordFailure([
        'job' => 'Test', 'queue' => 'default', 'exception' => 'Error', 'failed_at' => now()->timestamp,
    ]);

    $this->storage->purgeFailedOnly();

    // Failed should be gone
    expect(Redis::exists('qm-test:failed'))->toBe(0);

    // Running and throughput should still exist
    expect(Redis::exists('qm-test:running:purge-selective'))->toBe(1);
    $bucket = $this->storage->currentMinuteBucket();
    expect(Redis::exists("qm-test:throughput:default:{$bucket}"))->toBe(1);
});

it('getThroughput returns data for the requested time range', function () {
    // Manually seed a few throughput buckets
    $bucket = $this->storage->currentMinuteBucket();
    Redis::set("qm-test:throughput:default:{$bucket}", 42);

    $result = $this->storage->getThroughput('default', 5);

    expect($result)->toBeArray();
    expect(array_values($result))->toContain(42);
});

it('getFailedJobs respects the limit parameter', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->storage->recordFailure([
            'job' => "Job{$i}", 'queue' => 'default',
            'exception' => 'Error', 'failed_at' => now()->timestamp + $i,
        ]);
    }

    $limited = $this->storage->getFailedJobs(3);
    expect($limited)->toHaveCount(3);

    $all = $this->storage->getFailedJobs(100);
    expect($all)->toHaveCount(10);
});

it('truncates long exception messages', function () {
    $longException = str_repeat('A', 600);

    $this->storage->recordFailure([
        'job' => 'Test', 'queue' => 'default',
        'exception' => $longException, 'failed_at' => now()->timestamp,
    ]);

    $failures = $this->storage->getFailedJobs(1);

    expect(strlen($failures[0]['exception']))->toBeLessThan(520);
    expect($failures[0]['exception'])->toEndWith('... (truncated)');
});
