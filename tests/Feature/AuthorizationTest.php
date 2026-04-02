<?php

use Illuminate\Support\Facades\Gate;

use function DhavalPtel\QueueMonitor\Tests\Helpers\authorizedUser;
use function DhavalPtel\QueueMonitor\Tests\Helpers\regularUser;

it('allows authorized users to access the dashboard', function () {
    Gate::define('viewQueueMonitor', fn () => true);

    $response = $this->actingAs(authorizedUser())
        ->get('/queue-monitor');

    $response->assertOk();
});

it('blocks unauthorized users from the dashboard', function () {
    Gate::define('viewQueueMonitor', fn () => false);

    $response = $this->actingAs(regularUser())
        ->get('/queue-monitor');

    $response->assertForbidden();
});

it('blocks unauthorized users from API endpoints', function () {
    Gate::define('viewQueueMonitor', fn () => false);

    $endpoints = [
        '/queue-monitor/api/queues',
        '/queue-monitor/api/running',
        '/queue-monitor/api/failed',
        '/queue-monitor/api/throughput',
        '/queue-monitor/api/stats',
    ];

    foreach ($endpoints as $endpoint) {
        $response = $this->actingAs(regularUser())
            ->getJson($endpoint);

        $response->assertForbidden();
    }
});

it('allows authorized users to access all API endpoints', function () {
    Gate::define('viewQueueMonitor', fn () => true);

    $endpoints = [
        '/queue-monitor/api/queues',
        '/queue-monitor/api/running',
        '/queue-monitor/api/failed',
        '/queue-monitor/api/throughput',
        '/queue-monitor/api/stats',
    ];

    foreach ($endpoints as $endpoint) {
        $response = $this->actingAs(authorizedUser())
            ->getJson($endpoint);

        $response->assertOk();
    }
});
