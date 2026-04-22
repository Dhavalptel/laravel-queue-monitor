<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable / Disable Monitoring
    |--------------------------------------------------------------------------
    */
    'enabled' => env('QUEUE_MONITOR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Redis Connection
    |--------------------------------------------------------------------------
    |
    | The Redis connection to use for reading queue data and storing
    | monitoring metrics. This should match your queue Redis connection.
    |
    */
    'redis_connection' => env('QUEUE_MONITOR_REDIS_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Redis Key Prefix
    |--------------------------------------------------------------------------
    */
    'prefix' => env('QUEUE_MONITOR_PREFIX', 'queue-monitor'),

    /*
    |--------------------------------------------------------------------------
    | Queue Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix Laravel uses for queue keys in Redis. This is typically
    | set in config/database.php under redis.options.prefix. If your
    | queue keys look like "laravel_queues:default", set this to "laravel_".
    |
    */
    'queue_prefix' => env('QUEUE_MONITOR_QUEUE_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Queues to Monitor — per driver
    |--------------------------------------------------------------------------
    |
    | List queues under the driver that runs them.
    | Redis queues also support ['*'] for auto-discovery.
    | The legacy top-level 'queues' key is still honoured as redis queues.
    |
    */
    'connections' => [
        'redis' => [
            'queues' => ['default', 'emails', 'media', 'notifications'],
        ],
        'database' => [
            'queues' => ['orders', 'reports', 'imports', 'maintenance'],
            'table'  => 'jobs',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy queue list (treated as redis queues — kept for backward compat)
    |--------------------------------------------------------------------------
    */
    'queues' => [],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'running_job_ttl' => 7200,         // 2 hours
        'throughput_minutes' => 60,        // Keep 60 minutes of throughput buckets
        'failed_max_entries' => 1000,      // Trim failed job list to this size
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Settings
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'enabled' => true,
        'path' => 'queue-monitor',
        'middleware' => ['web'],
        'polling_interval' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Gate
    |--------------------------------------------------------------------------
    |
    | Define who can access the dashboard. In non-local environments,
    | register a gate named 'viewQueueMonitor' in your AuthServiceProvider.
    |
    */
    'gate' => 'viewQueueMonitor',

    /*
    |--------------------------------------------------------------------------
    | Alerts (Optional)
    |--------------------------------------------------------------------------
    */
    'alerts' => [
        'backlog_threshold' => 500,
        'failure_rate_threshold' => 5.0,
        'job_duration_threshold' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Historical Snapshots (Optional)
    |--------------------------------------------------------------------------
    */
    'snapshot' => [
        'enabled' => false,
        'interval_minutes' => 5,
    ],
];
