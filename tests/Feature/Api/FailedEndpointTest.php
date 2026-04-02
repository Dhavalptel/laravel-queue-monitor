<?php

use DhavalPtel\QueueMonitor\Storage\RedisStorage;
use Illuminate\Support\Facades\Gate;

use function DhavalPtel\QueueMonitor\Tests\Helpers\authorizedUser;

beforeEach(function () {
    Gate::define('viewQueueMonitor', fn () => true);
});

it('returns failed jobs as JSON', function () {
    $storage = app(RedisStorage::class);
    $storage->recordFailure([
        'job' => 'App\\Jobs\\ImportData',
        'queue' => 'default',
        'exception' => 'File not found',
        'failed_at' => now()->timestamp,
        'attempts' => 3,
        'max_tries' => 3,
    ]);

    $response = $this->actingAs(authorizedUser())
        ->getJson('/queue-monitor/api/failed');

    $response->assertOk()
        ->assertJsonStructure(['jobs', 'total']);

    expect($response->json('total'))->toBe(1);
    expect($response->json('jobs.0.exception'))->toBe('File not found');
});

it('respects the limit query parameter', function () {
    $storage = app(RedisStorage::class);
    for ($i = 0; $i < 10; $i++) {
        $storage->recordFailure([
            'job' => "Job{$i}", 'queue' => 'default',
            'exception' => 'Error', 'failed_at' => now()->timestamp + $i,
        ]);
    }

    $response = $this->actingAs(authorizedUser())
        ->getJson('/queue-monitor/api/failed?limit=3');

    $response->assertOk();
    expect($response->json('total'))->toBe(3);
});

it('returns empty when no failures exist', function () {
    $response = $this->actingAs(authorizedUser())
        ->getJson('/queue-monitor/api/failed');

    $response->assertOk();
    expect($response->json('total'))->toBe(0);
});
