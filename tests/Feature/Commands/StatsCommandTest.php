<?php

it('displays queue stats in the terminal', function () {
    $this->artisan('queue-monitor:stats')
        ->assertSuccessful()
        ->expectsOutputToContain('Queue Monitor');
});

it('fails gracefully when monitoring is disabled', function () {
    config(['queue-monitor.enabled' => false]);

    $this->artisan('queue-monitor:stats')
        ->assertFailed()
        ->expectsOutput('Queue monitoring is disabled.');

    config(['queue-monitor.enabled' => true]);
});
