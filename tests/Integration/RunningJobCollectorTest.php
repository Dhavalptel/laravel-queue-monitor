<?php

use DhavalPtel\QueueMonitor\Collectors\RunningJobCollector;
use DhavalPtel\QueueMonitor\Listeners\JobProcessingListener;
use DhavalPtel\QueueMonitor\Storage\RedisStorage;
use DhavalPtel\QueueMonitor\Tests\Helpers\EventFactory;

beforeEach(function () {
    $this->storage = app(RedisStorage::class);
    $this->collector = new RunningJobCollector($this->storage);
    $this->listener = new JobProcessingListener($this->storage);
});

it('returns empty array when no jobs are running', function () {
    expect($this->collector->collect())->toBeArray()->toBeEmpty();
});

it('returns running jobs after processing events', function () {
    $this->listener->handle(EventFactory::jobProcessing(
        jobClass: 'App\\Jobs\\ProcessPayment',
        queue: 'high',
        jobId: 'run-1',
    ));

    $this->listener->handle(EventFactory::jobProcessing(
        jobClass: 'App\\Jobs\\SendEmail',
        queue: 'emails',
        jobId: 'run-2',
    ));

    $result = $this->collector->collect();

    expect($result)->toHaveCount(2);

    $jobClasses = array_column($result, 'job');
    expect($jobClasses)->toContain('App\\Jobs\\ProcessPayment', 'App\\Jobs\\SendEmail');
});

it('includes duration_seconds in running jobs', function () {
    $this->listener->handle(EventFactory::jobProcessing(jobId: 'run-dur'));

    $result = $this->collector->collect();

    expect($result[0])
        ->toHaveKey('duration_seconds')
        ->and($result[0]['duration_seconds'])->toBeGreaterThanOrEqual(0);
});

it('sorts running jobs by duration descending', function () {
    // Simulate two jobs started at different times
    $this->storage->markJobRunning('run-old', [
        'job' => 'App\\Jobs\\OldJob',
        'queue' => 'default',
        'started_at' => now()->subMinutes(5)->timestamp,
        'attempt' => 1,
    ]);

    $this->storage->markJobRunning('run-new', [
        'job' => 'App\\Jobs\\NewJob',
        'queue' => 'default',
        'started_at' => now()->timestamp,
        'attempt' => 1,
    ]);

    $result = $this->collector->collect();

    expect($result[0]['job'])->toBe('App\\Jobs\\OldJob');
    expect($result[1]['job'])->toBe('App\\Jobs\\NewJob');
});
