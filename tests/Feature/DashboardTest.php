<?php

use Illuminate\Support\Facades\Gate;

use function DhavalPtel\QueueMonitor\Tests\Helpers\authorizedUser;

beforeEach(function () {
    Gate::define('viewQueueMonitor', fn () => true);
});

it('loads the dashboard view', function () {
    $response = $this->actingAs(authorizedUser())
        ->get('/queue-monitor');

    $response->assertOk();
    $response->assertSee('Queue Monitor');
    $response->assertSee('queueMonitor()');
});

it('contains Alpine.js initialization', function () {
    $response = $this->actingAs(authorizedUser())
        ->get('/queue-monitor');

    $response->assertSee('x-data="queueMonitor()"');
    $response->assertSee('alpinejs');
});

it('includes the configured polling interval', function () {
    config(['queue-monitor.dashboard.polling_interval' => 5]);

    $response = $this->actingAs(authorizedUser())
        ->get('/queue-monitor');

    $response->assertSee('Polling every 5s');
});
