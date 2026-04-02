<?php

namespace DhavalPtel\QueueMonitor\Events;

use Illuminate\Foundation\Events\Dispatchable;

class FailureRateExceeded
{
    use Dispatchable;

    public function __construct(
        public float $rate,
        public float $threshold,
    ) {}
}
