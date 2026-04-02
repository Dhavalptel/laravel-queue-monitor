<?php

namespace DhavalPtel\QueueMonitor\Events;

use Illuminate\Foundation\Events\Dispatchable;

class QueueBacklogDetected
{
    use Dispatchable;

    public function __construct(
        public string $queue,
        public int $size,
        public int $threshold,
    ) {}
}
