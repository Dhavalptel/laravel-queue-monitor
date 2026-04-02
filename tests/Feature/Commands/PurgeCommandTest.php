<?php

use DhavalPtel\QueueMonitor\Storage\RedisStorage;
use Illuminate\Support\Facades\Redis;

it('purges all monitoring data', function () {
    $storage = app(RedisStorage::class);
    $storage->markJobRunning('cmd-purge-1', [
        'job' => 'Test', 'queue' => 'default', 'started_at' => now()->timestamp, 'attempt' => 1,
    ]);
    $storage->incrementThroughput('default');
    $storage->recordFailure([
        'job' => 'Test', 'queue' => 'default', 'exception' => 'Error', 'failed_at' => now()->timestamp,
    ]);

    $this->artisan('queue-monitor:purge')
        ->assertSuccessful()
        ->expectsOutput('All monitoring data purged.');

    expect(Redis::keys('qm-test:*'))->toBeEmpty();
});

it('purges only failed jobs with --failed-only flag', function () {
    $storage = app(RedisStorage::class);
    $storage->markJobRunning('cmd-purge-2', [
        'job' => 'Test', 'queue' => 'default', 'started_at' => now()->timestamp, 'attempt' => 1,
    ]);
    $storage->recordFailure([
        'job' => 'Test', 'queue' => 'default', 'exception' => 'Error', 'failed_at' => now()->timestamp,
    ]);

    $this->artisan('queue-monitor:purge --failed-only')
        ->assertSuccessful()
        ->expectsOutput('Failed job records purged.');

    expect(Redis::exists('qm-test:failed'))->toBe(0);
    expect(Redis::exists('qm-test:running:cmd-purge-2'))->toBe(1);
});
