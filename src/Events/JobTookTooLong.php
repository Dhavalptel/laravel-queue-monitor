<?php

namespace DhavalPtel\QueueMonitor\Events;

use Illuminate\Foundation\Events\Dispatchable;

class JobTookTooLong
{
    use Dispatchable;

    public function __construct(
        public string $jobId,
        public string $jobClass,
        public string $queue,
        public float $durationSeconds,
        public float $threshold,
    ) {}
}
