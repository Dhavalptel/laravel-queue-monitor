<?php

it('loads default configuration values', function () {
    expect(config('queue-monitor.enabled'))->toBeTrue();
    expect(config('queue-monitor.prefix'))->toBe('qm-test');
    expect(config('queue-monitor.retention.running_job_ttl'))->toBe(7200);
    expect(config('queue-monitor.retention.throughput_minutes'))->toBe(60);
    expect(config('queue-monitor.retention.failed_max_entries'))->toBe(100);
    expect(config('queue-monitor.dashboard.enabled'))->toBeTrue();
    expect(config('queue-monitor.dashboard.path'))->toBe('queue-monitor');
    expect(config('queue-monitor.dashboard.polling_interval'))->toBe(3);
});

it('has queues configured for testing', function () {
    expect(config('queue-monitor.queues'))
        ->toBeArray()
        ->toContain('default', 'high', 'emails');
});

it('has gate configured', function () {
    expect(config('queue-monitor.gate'))->toBe('viewQueueMonitor');
});
