<?php

use DhavalPtel\QueueMonitor\Storage\RedisStorage;
use Illuminate\Support\Facades\Gate;

use function DhavalPtel\QueueMonitor\Tests\Helpers\authorizedUser;

beforeEach(function () {
    Gate::define('viewQueueMonitor', fn () => true);
});

it('returns throughput data as JSON', function () {
    $storage = app(RedisStorage::class);
    $storage->incrementThroughput('default');
    $storage->incrementThroughput('default');
    $storage->incrementThroughput('high');

    $response = $this->actingAs(authorizedUser())
        ->getJson('/queue-monitor/api/throughput?minutes=5');

    $response->assertOk()
        ->assertJsonStructure(['queues', 'minutes']);

    expect($response->json('minutes'))->toBe(5);
    expect($response->json('queues'))->toHaveKeys(['default', 'high', 'emails']);
});

it('caps minutes parameter at 1440', function () {
    $response = $this->actingAs(authorizedUser())
        ->getJson('/queue-monitor/api/throughput?minutes=99999');

    $response->assertOk();
    expect($response->json('minutes'))->toBe(1440);
});
