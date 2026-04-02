<?php

namespace DhavalPtel\QueueMonitor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array queueSizes()
 * @method static array runningJobs()
 * @method static array failedJobs(int $limit = 50)
 * @method static array throughput(string $queue = 'default', int $minutes = 60)
 * @method static array allThroughput(int $minutes = 60)
 * @method static array stats()
 * @method static void purgeAll()
 * @method static void purgeFailedOnly()
 * @method static bool isEnabled()
 *
 * @see \DhavalPtel\QueueMonitor\QueueMonitor
 */
class QueueMonitor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \DhavalPtel\QueueMonitor\QueueMonitor::class;
    }
}
