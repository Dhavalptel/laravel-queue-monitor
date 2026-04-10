<?php

namespace DhavalPtel\QueueMonitor\Listeners;

use DhavalPtel\QueueMonitor\Storage\RedisStorage;
use Illuminate\Queue\Events\JobFailed;

class JobFailedListener
{
    public function __construct(
        protected RedisStorage $storage,
    ) {}

    /**
     * Handle the Queue::failing event.
     *
     * Called when a job has failed after all retries.
     */
    public function handle(JobFailed $event): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        $jobId = $event->job->getJobId();

        if (empty($jobId)) {
            return;
        }
        
        $queue = $event->job->getQueue() ?? 'default';

        // Remove from running jobs (if still there)
        $this->storage->markJobCompleted($jobId, $queue);

        // Record the failure
        $this->storage->markJobFailed($jobId, [
            'job'        => $event->job->resolveName(),
            'queue'      => $queue,
            'exception'  => $event->exception ? $event->exception->getMessage() : 'Unknown error',
            'failed_at'  => now()->timestamp,
            'attempts'   => $event->job->attempts(),
            'max_tries'  => $event->job->maxTries(),
        ]);
    }
}
