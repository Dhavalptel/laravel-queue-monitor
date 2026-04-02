<?php

use Illuminate\Support\Facades\Gate;

use function DhavalPtel\QueueMonitor\Tests\Helpers\authorizedUser;

beforeEach(function () {
    Gate::define('viewQueueMonitor', fn () => true);
});

it('returns aggregate stats as JSON', function () {
    $response = $this->actingAs(authorizedUser())
        ->getJson('/queue-monitor/api/stats');

    $response->assertOk()
        ->assertJsonStructure([
            'processed_per_hour',
            'total_pending',
            'running_count',
            'failed_count',
            'failure_rate',
            'avg_wait_seconds',
        ]);
});

it('returns numeric values for all stats', function () {
    $response = $this->actingAs(authorizedUser())
        ->getJson('/queue-monitor/api/stats');

    $data = $response->json();

    expect($data['processed_per_hour'])->toBeInt();
    expect($data['total_pending'])->toBeInt();
    expect($data['running_count'])->toBeInt();
    expect($data['failed_count'])->toBeInt();
    expect($data['failure_rate'])->toBeNumeric();
    expect($data['avg_wait_seconds'])->toBeNumeric();
});
