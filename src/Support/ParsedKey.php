<?php

namespace DhavalPtel\QueueMonitor\Support;

class ParsedKey
{
    public function __construct(
        public readonly string $queue,
        public readonly string $type,
    ) {}
}
