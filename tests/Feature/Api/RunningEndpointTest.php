<?php

use DhavalPtel\QueueMonitor\Storage\RedisStorage;
use Illuminate\Support\Facades\Gate;

use function DhavalPtel\QueueMonitor\Tests\Helpers\authorizedUser;

beforeEach(function () {
    Gate::define('viewQueueMonitor', fn () => true);
});

it('returns running jobs as JSON', function () {
    $storage = app(RedisStorage::class);
    $storage->markJobRunning('api-run-1', [
        'job' => 'App\\Jobs\\ProcessPayment',
        'queue' => 'high',
        'started_at' => now()->subSeconds(5)->timestamp,
        'attempt' => 1,
    ]);

    $response = $this->actingAs(authorizedUser())
        ->getJson('/queue-monitor/api/running');

    $response->assertOk()
        ->assertJsonStructure(['jobs', 'total']);

    expect($response->json('total'))->toBe(1);
    expect($response->json('jobs.0.job'))->toBe('App\\Jobs\\ProcessPayment');
});

it('returns empty when no jobs are running', function () {
    $response = $this->actingAs(authorizedUser())
        ->getJson('/queue-monitor/api/running');

    $response->assertOk();
    expect($response->json('total'))->toBe(0);
    expect($response->json('jobs'))->toBeEmpty();
});
