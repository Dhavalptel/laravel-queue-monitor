<?php

namespace DhavalPtel\QueueMonitor;

use DhavalPtel\QueueMonitor\Collectors\DatabaseQueueSizeCollector;
use DhavalPtel\QueueMonitor\Collectors\FailedJobCollector;
use DhavalPtel\QueueMonitor\Collectors\RedisQueueSizeCollector;
use DhavalPtel\QueueMonitor\Collectors\RunningJobCollector;
use DhavalPtel\QueueMonitor\Collectors\UnifiedQueueSizeCollector;
use DhavalPtel\QueueMonitor\Commands\PurgeCommand;
use DhavalPtel\QueueMonitor\Commands\SnapshotCommand;
use DhavalPtel\QueueMonitor\Commands\StatsCommand;
use DhavalPtel\QueueMonitor\Contracts\StorageDriver;
use DhavalPtel\QueueMonitor\Listeners\JobFailedListener;
use DhavalPtel\QueueMonitor\Listeners\JobProcessedListener;
use DhavalPtel\QueueMonitor\Listeners\JobProcessingListener;
use DhavalPtel\QueueMonitor\Storage\MetricsAggregator;
use DhavalPtel\QueueMonitor\Storage\RedisStorage;
use DhavalPtel\QueueMonitor\Support\RedisKeyParser;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class QueueMonitorServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/queue-monitor.php', 'queue-monitor');

        $this->app->singleton(RedisKeyParser::class);
        $this->app->singleton(RedisStorage::class);
        $this->app->singleton(RedisQueueSizeCollector::class);
        $this->app->singleton(DatabaseQueueSizeCollector::class);
        $this->app->singleton(UnifiedQueueSizeCollector::class);
        $this->app->singleton(RunningJobCollector::class);
        $this->app->singleton(FailedJobCollector::class);
        $this->app->singleton(MetricsAggregator::class);

        $this->app->bind(StorageDriver::class, RedisStorage::class);

        $this->app->singleton(QueueMonitor::class, function ($app) {
            return new QueueMonitor(
                $app->make(RedisStorage::class),
                $app->make(UnifiedQueueSizeCollector::class),
                $app->make(RunningJobCollector::class),
                $app->make(FailedJobCollector::class),
                $app->make(MetricsAggregator::class),
            );
        });
    }

    /**
     * Bootstrap the package services.
     */
    public function boot(): void
    {
        $this->registerPublishables();
        $this->registerRoutes();
        $this->registerListeners();
        $this->registerCommands();
        $this->registerGate();
    }

    /**
     * Register publishable assets.
     */
    protected function registerPublishables(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/queue-monitor.php' => config_path('queue-monitor.php'),
            ], 'queue-monitor-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/queue-monitor'),
            ], 'queue-monitor-views');

            if (config('queue-monitor.snapshot.enabled', false)) {
                $this->publishes([
                    __DIR__ . '/../database/migrations' => database_path('migrations'),
                ], 'queue-monitor-migrations');
            }
        }
    }

    /**
     * Register the dashboard and API routes.
     */
    protected function registerRoutes(): void
    {
        if (! config('queue-monitor.dashboard.enabled', true)) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'queue-monitor');

        $this->loadRoutesFrom(__DIR__ . '/Http/Routes/web.php');
    }

    /**
     * Register queue event listeners.
     */
    protected function registerListeners(): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        Event::listen(JobProcessing::class, JobProcessingListener::class);
        Event::listen(JobProcessed::class, JobProcessedListener::class);
        Event::listen(JobFailed::class, JobFailedListener::class);
    }

    /**
     * Register Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PurgeCommand::class,
                SnapshotCommand::class,
                StatsCommand::class,
            ]);
        }
    }

    /**
     * Register the default authorization gate.
     */
    protected function registerGate(): void
    {
        Gate::define(
            config('queue-monitor.gate', 'viewQueueMonitor'),
            function ($user = null) {
                return $this->app->environment('local');
            }
        );
    }
}
