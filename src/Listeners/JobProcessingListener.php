<?php

namespace DhavalPtel\QueueMonitor\Listeners;

use DhavalPtel\QueueMonitor\Storage\RedisStorage;
use Illuminate\Queue\Events\JobProcessing;

class JobProcessingListener
{
    public function __construct(
        protected RedisStorage $storage,
    ) {}

    /**
     * Handle the Queue::before event.
     *
     * Called when a worker picks up a job and begins processing it.
     */
    public function handle(JobProcessing $event): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        $jobId = $event->job->getJobId();

        if (empty($jobId)) {
            return;
        }

        $this->storage->markJobRunning($jobId, [
            'job' => $event->job->resolveName(),
            'queue' => $event->job->getQueue() ?? 'default',
            'started_at' => now()->timestamp,
            'attempt' => $event->job->attempts(),
            'connection' => $event->connectionName,
        ]);
    }
}
