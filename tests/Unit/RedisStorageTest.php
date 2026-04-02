<?php

use DhavalPtel\QueueMonitor\Storage\RedisStorage;

it('generates correct key prefixes', function () {
    $storage = app(RedisStorage::class);

    expect($storage->key('running', 'abc123'))
        ->toBe('qm-test:running:abc123');

    expect($storage->key('throughput', 'default', '202503311422'))
        ->toBe('qm-test:throughput:default:202503311422');
});

it('generates the current minute bucket in expected format', function () {
    $storage = app(RedisStorage::class);
    $bucket = $storage->currentMinuteBucket();

    expect($bucket)
        ->toMatch('/^\d{12}$/')
        ->toStartWith(now()->format('Ymd'));
});
