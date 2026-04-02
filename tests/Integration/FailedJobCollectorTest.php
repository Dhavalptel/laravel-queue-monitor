<?php

use DhavalPtel\QueueMonitor\Collectors\FailedJobCollector;
use DhavalPtel\QueueMonitor\Listeners\JobFailedListener;
use DhavalPtel\QueueMonitor\Storage\RedisStorage;
use DhavalPtel\QueueMonitor\Tests\Helpers\EventFactory;

beforeEach(function () {
    $this->storage = app(RedisStorage::class);
    $this->collector = new FailedJobCollector($this->storage);
    $this->listener = new JobFailedListener($this->storage);
});

it('returns empty array when no failures exist', function () {
    expect($this->collector->collect())->toBeArray()->toBeEmpty();
});

it('collects failed jobs after failure events', function () {
    $this->listener->handle(EventFactory::jobFailed(
        jobClass: 'App\\Jobs\\ImportData',
        queue: 'default',
        jobId: 'fail-collect-1',
        exceptionMessage: 'File not found',
    ));

    $result = $this->collector->collect();

    expect($result)->toHaveCount(1);
    expect($result[0])
        ->job->toBe('App\\Jobs\\ImportData')
        ->exception->toBe('File not found');
});

it('returns failures in reverse chronological order', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->listener->handle(EventFactory::jobFailed(
            jobClass: "App\\Jobs\\Job{$i}",
            jobId: "fail-order-{$i}",
        ));
        usleep(10000);
    }

    $result = $this->collector->collect();

    expect($result)->toHaveCount(3);
    // Newest first
    expect($result[0]['job'])->toBe('App\\Jobs\\Job2');
    expect($result[2]['job'])->toBe('App\\Jobs\\Job0');
});
