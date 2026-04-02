<?php

use DhavalPtel\QueueMonitor\Collectors\RedisQueueSizeCollector;
use DhavalPtel\QueueMonitor\Support\RedisKeyParser;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->collector = new RedisQueueSizeCollector(new RedisKeyParser());
});

it('reads pending count using LLEN', function () {
    // Push some fake jobs into the default queue
    for ($i = 0; $i < 5; $i++) {
        Redis::rpush('queues:default', json_encode(['uuid' => "fake-{$i}"]));
    }

    expect($this->collector->getPendingCount('default'))->toBe(5);
});

it('reads delayed count using ZCARD', function () {
    for ($i = 0; $i < 3; $i++) {
        Redis::zadd('queues:high:delayed', now()->addMinutes($i)->timestamp, json_encode(['uuid' => "delayed-{$i}"]));
    }

    expect($this->collector->getDelayedCount('high'))->toBe(3);
});

it('reads reserved count using ZCARD', function () {
    for ($i = 0; $i < 2; $i++) {
        Redis::zadd('queues:emails:reserved', now()->timestamp, json_encode(['uuid' => "reserved-{$i}"]));
    }

    expect($this->collector->getReservedCount('emails'))->toBe(2);
});

it('returns zero for empty queues', function () {
    expect($this->collector->getPendingCount('nonexistent'))->toBe(0);
    expect($this->collector->getDelayedCount('nonexistent'))->toBe(0);
    expect($this->collector->getReservedCount('nonexistent'))->toBe(0);
});

it('collects all configured queues', function () {
    // Seed some data
    Redis::rpush('queues:default', json_encode(['uuid' => 'a']));
    Redis::rpush('queues:high', json_encode(['uuid' => 'b']));
    Redis::rpush('queues:high', json_encode(['uuid' => 'c']));

    $result = $this->collector->collect();

    expect($result)->toBeArray()->toHaveCount(3);

    $names = array_column($result, 'name');
    expect($names)->toContain('default', 'high', 'emails');

    $defaultQueue = collect($result)->firstWhere('name', 'default');
    expect($defaultQueue['pending'])->toBe(1);

    $highQueue = collect($result)->firstWhere('name', 'high');
    expect($highQueue['pending'])->toBe(2);
});

it('marks queue status as healthy when below threshold', function () {
    config(['queue-monitor.alerts.backlog_threshold' => 500]);

    Redis::rpush('queues:default', json_encode(['uuid' => 'x']));

    $result = $this->collector->collect();
    $defaultQueue = collect($result)->firstWhere('name', 'default');

    expect($defaultQueue['status'])->toBe('healthy');
});
