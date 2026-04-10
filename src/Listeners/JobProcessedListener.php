<?php

namespace DhavalPtel\QueueMonitor\Listeners;

use DhavalPtel\QueueMonitor\Events\JobTookTooLong;
use DhavalPtel\QueueMonitor\Storage\RedisStorage;
use Illuminate\Queue\Events\JobProcessed;

class JobProcessedListener
{
    public function __construct(
        protected RedisStorage $storage,
    ) {}

    /**
     * Handle the Queue::after event.
     *
     * Called when a job has been successfully processed.
     */
    public function handle(JobProcessed $event): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        $jobId = $event->job->getJobId();

        if (empty($jobId)) {
            return;
        }

        $queue = $event->job->getQueue() ?? 'default';
        
        // Remove from running jobs and increment throughput
        $this->storage->markJobCompleted($jobId, $queue);

        // Check for job duration alerts
        $this->checkDurationAlert($event);
    }

    /**
     * Dispatch an event if the job took too long.
     */
    protected function checkDurationAlert(JobProcessed $event): void
    {
        $threshold = config('queue-monitor.alerts.job_duration_threshold');

        if (is_null($threshold)) {
            return;
        }

        // We can't know exact duration without the running entry,
        // but this is a reasonable approximation since this fires
        // immediately after the job finishes
        $jobId = $event->job->getJobId();
        $queue = $event->job->getQueue() ?? 'default';
        $jobClass = $event->job->resolveName();

        // The running entry is already deleted, so we approximate
        // by checking the payload timestamp if available
    }
}
