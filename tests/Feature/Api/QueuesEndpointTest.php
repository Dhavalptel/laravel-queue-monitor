<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redis;

use function DhavalPtel\QueueMonitor\Tests\Helpers\authorizedUser;

beforeEach(function () {
    Gate::define('viewQueueMonitor', fn () => true);
});

it('returns queue sizes as JSON', function () {
    Redis::rpush('queues:default', json_encode(['uuid' => 'a']));
    Redis::rpush('queues:default', json_encode(['uuid' => 'b']));
    Redis::rpush('queues:high', json_encode(['uuid' => 'c']));

    $response = $this->actingAs(authorizedUser())
        ->getJson('/queue-monitor/api/queues');

    $response->assertOk()
        ->assertJsonStructure(['queues' => [['name', 'pending', 'delayed', 'reserved', 'status']]]);

    $queues = $response->json('queues');
    $default = collect($queues)->firstWhere('name', 'default');

    expect($default['pending'])->toBe(2);
});

it('includes all configured queues even if empty', function () {
    $response = $this->actingAs(authorizedUser())
        ->getJson('/queue-monitor/api/queues');

    $response->assertOk();

    $names = array_column($response->json('queues'), 'name');
    expect($names)->toContain('default', 'high', 'emails');
});
