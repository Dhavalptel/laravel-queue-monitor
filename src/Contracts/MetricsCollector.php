<?php

namespace DhavalPtel\QueueMonitor\Contracts;

interface MetricsCollector
{
    /**
     * Collect metrics and return as an array.
     */
    public function collect(): array;
}
