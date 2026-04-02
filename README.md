# Laravel Queue Monitor

A lightweight, memory-efficient queue monitoring package for Laravel that reads directly from Redis. No daemon process, no supervisor, no npm build step — just drop it in and go.

---

## Why not Horizon?

Laravel Horizon is a powerful tool, but it comes with a cost. It runs a persistent PHP supervisor process that forks dedicated workers, intercepts every job event, and stores full job payloads in Redis. On a typical production server, that's **70–250 MB of memory** just for monitoring.

**Queue Monitor takes a fundamentally different approach.**

| | Horizon | Queue Monitor |
|---|---|---|
| **Architecture** | Persistent supervisor daemon | Event listeners inside your existing app |
| **PHP process** | Dedicated (50–150 MB RAM) | None — zero extra processes |
| **Redis storage** | Full job payloads (20–100 MB) | Compact hashes with TTL (2–5 MB) |
| **Worker management** | Built-in supervisor with auto-scaling | You manage workers yourself (`queue:work`) |
| **Dashboard** | Compiled Vue SPA (requires npm build) | Single Blade file with Alpine.js (no build) |
| **Queue size reads** | Intercepted through supervisor | Direct Redis `LLEN` / `ZCARD` (O(1)) |
| **Job payload storage** | Full serialized payload per job | ~200 bytes per job (class, queue, timestamps) |
| **Data retention** | Manual pruning or unbounded growth | Automatic TTL-based expiry on every key |

### What you get

- Real-time queue sizes, throughput, and failure rates
- Currently running jobs with duration tracking
- Recent failures with exception messages
- A clean, auto-refreshing dashboard with zero build tooling
- Memory footprint ~50x smaller than Horizon

### What you trade away

- No built-in worker supervisor or auto-scaling (use your own process manager)
- No retry/delete jobs from the dashboard UI
- No full job payload inspection
- No long-term historical metrics (unless you enable optional snapshots)

If you need a lightweight monitoring layer on top of your existing `queue:work` setup, this package is for you.

---

## Requirements

- PHP 8.1+
- Laravel 10.x, 11.x, 12.x, or 13.x
- Redis as your queue driver (`QUEUE_CONNECTION=redis`)
- `ext-redis` or `predis/predis`

---

## Installation

### Step 1: Install via Composer

```bash
composer require yourvendor/laravel-queue-monitor
```

The package uses Laravel's auto-discovery, so the service provider registers automatically.

### Step 2: Publish the config file

```bash
php artisan vendor:publish --tag=queue-monitor-config
```

This creates `config/queue-monitor.php`.

### Step 3: (Optional) Run migrations for historical snapshots

Only needed if you want to persist historical metrics to the database:

```bash
php artisan migrate
```

### Step 4: Access the dashboard

Navigate to `/queue-monitor` in your browser. That's it.

---

## Configuration

After publishing, edit `config/queue-monitor.php`:

```php
return [

    /*
    |--------------------------------------------------------------------------
    | Enable / Disable Monitoring
    |--------------------------------------------------------------------------
    |
    | Set to false to completely disable all event listeners and data
    | collection. The dashboard will still load but show no data.
    |
    */
    'enabled' => env('QUEUE_MONITOR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Redis Key Prefix
    |--------------------------------------------------------------------------
    |
    | All monitoring keys in Redis are stored under this prefix.
    | Change this if you have multiple apps sharing the same Redis instance.
    |
    */
    'prefix' => env('QUEUE_MONITOR_PREFIX', 'queue-monitor'),

    /*
    |--------------------------------------------------------------------------
    | Queues to Monitor
    |--------------------------------------------------------------------------
    |
    | Use ['*'] to auto-discover and monitor all queues.
    | Or specify an explicit list: ['default', 'high', 'emails']
    |
    */
    'queues' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | Controls how long monitoring data is kept in Redis.
    | All keys are set with a TTL and expire automatically.
    |
    */
    'retention' => [
        'running_job_ttl' => 7200,       // 2 hours (safety net if worker crashes)
        'throughput_minutes' => 60,       // Keep 60 minutes of throughput buckets
        'failed_max_entries' => 1000,     // Trim failed job list to this size
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Settings
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'enabled' => true,
        'path' => 'queue-monitor',
        'middleware' => ['web', 'auth'],
        'polling_interval' => 3,          // Seconds between API polls
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Gate
    |--------------------------------------------------------------------------
    |
    | Define who can access the dashboard. By default, only local
    | environments allow access. Override in AuthServiceProvider.
    |
    */
    'gate' => 'viewQueueMonitor',

    /*
    |--------------------------------------------------------------------------
    | Historical Snapshots (Optional)
    |--------------------------------------------------------------------------
    |
    | When enabled, a scheduled command writes queue metrics to the
    | database at the configured interval. This enables the dashboard
    | to show historical charts beyond the Redis retention window.
    |
    */
    'snapshot' => [
        'enabled' => false,
        'interval_minutes' => 5,
    ],
];
```

---

## Authorization

By default, the dashboard is only accessible in `local` environments. To grant access in production, define a gate in your `AuthServiceProvider` (or `AppServiceProvider` in Laravel 11+):

```php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewQueueMonitor', function ($user) {
        return in_array($user->email, [
            'admin@yourcompany.com',
        ]);
    });
}
```

---

## How It Works

Queue Monitor does not run any background processes. It hooks into three Laravel queue events that fire inside your existing worker processes.

### 1. Job starts → `Queue::before`

When a worker picks up a job, the `JobProcessingListener` writes a small Redis hash:

```
queue-monitor:running:{jobId} => {
    "job": "App\\Jobs\\ProcessPayment",
    "queue": "high",
    "started_at": 1711900000,
    "attempt": 1
}
TTL: 7200 seconds
```

This is ~200 bytes. If the worker crashes, the key expires on its own.

### 2. Job completes → `Queue::after`

The `JobProcessedListener` deletes the running key and increments a throughput counter:

```
DEL   queue-monitor:running:{jobId}
INCR  queue-monitor:throughput:high:202503311422
```

Throughput keys are bucketed by minute and auto-expire after the configured retention window.

### 3. Job fails → `Queue::failing`

The `JobFailedListener` pushes a compact failure record into a sorted set:

```
ZADD queue-monitor:failed {timestamp} {json}
```

The sorted set is trimmed to the configured `failed_max_entries` on every write.

### 4. Queue sizes → Direct Redis reads

Queue sizes are never intercepted or cached. Every API request reads live from Redis:

```
LLEN  queues:default              → pending jobs
ZCARD queues:default:delayed      → delayed jobs
ZCARD queues:default:reserved     → reserved (being processed)
```

These are O(1) operations in Redis — instant regardless of queue size.

---

## Dashboard

The dashboard is a single Blade file that uses Alpine.js and Tailwind CSS via CDN. No npm, no build step, no compiled assets.

It polls the JSON API every 3 seconds (configurable) and renders:

- **Stats bar** — Jobs processed/hr, total pending, running now, failed count, avg wait time
- **Queue cards** — One per queue with pending/delayed/reserved counts, health status, and sparklines
- **Throughput chart** — Jobs per minute over the retention window
- **Running jobs table** — Live view with job class, queue, duration, and attempt number
- **Failed jobs table** — Recent failures with exception messages and timestamps
- **Footer** — Redis memory used by monitoring, polling interval, retention window

### Customizing the dashboard

Publish the view to override it:

```bash
php artisan vendor:publish --tag=queue-monitor-views
```

This copies the Blade file to `resources/views/vendor/queue-monitor/dashboard.blade.php`.

---

## API Endpoints

All endpoints are prefixed with your configured path (default: `/queue-monitor/api`). They return JSON and are protected by the same middleware and gate as the dashboard.

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/queue-monitor/api/queues` | All queues with pending, delayed, reserved counts |
| `GET` | `/queue-monitor/api/running` | Currently processing jobs with duration |
| `GET` | `/queue-monitor/api/failed` | Recent failures with exceptions |
| `GET` | `/queue-monitor/api/throughput` | Jobs processed per minute (bucketed) |
| `GET` | `/queue-monitor/api/stats` | Aggregate stats: total processed, failure rate, avg wait |

### Example response: `/api/queues`

```json
{
    "queues": [
        {
            "name": "default",
            "pending": 42,
            "delayed": 18,
            "reserved": 3,
            "status": "healthy"
        },
        {
            "name": "high",
            "pending": 87,
            "delayed": 5,
            "reserved": 4,
            "status": "backlog"
        }
    ]
}
```

### Example response: `/api/running`

```json
{
    "jobs": [
        {
            "id": "abc123",
            "job": "App\\Jobs\\ProcessPayment",
            "queue": "high",
            "started_at": "2025-03-31T14:22:00Z",
            "duration_seconds": 4.2,
            "attempt": 1
        }
    ],
    "total": 7
}
```

### Example response: `/api/failed`

```json
{
    "jobs": [
        {
            "job": "App\\Jobs\\ChargeSubscription",
            "queue": "high",
            "exception": "Stripe\\Exception\\CardException: Your card was declined.",
            "failed_at": "2025-03-31T14:20:00Z",
            "attempts": 3,
            "max_tries": 3
        }
    ],
    "total": 3
}
```

---

## Programmatic Usage

You can access all metrics from your application code without the dashboard:

```php
use YourVendor\QueueMonitor\Facades\QueueMonitor;

// Get all queue sizes
$queues = QueueMonitor::queueSizes();
// Returns: ['default' => ['pending' => 42, 'delayed' => 18, 'reserved' => 3], ...]

// Get currently running jobs
$running = QueueMonitor::runningJobs();

// Get recent failures
$failed = QueueMonitor::failedJobs(limit: 50);

// Get throughput (jobs per minute for last 30 minutes)
$throughput = QueueMonitor::throughput(minutes: 30);

// Get aggregate stats
$stats = QueueMonitor::stats();
// Returns: ['processed_per_hour' => 2847, 'failure_rate' => 0.1, 'avg_wait_seconds' => 1.2]
```

### Use in health checks

```php
// In a health check endpoint or monitoring integration
Route::get('/health/queues', function () {
    $sizes = QueueMonitor::queueSizes();
    $maxPending = max(array_column($sizes, 'pending'));

    return response()->json([
        'healthy' => $maxPending < 1000,
        'max_pending' => $maxPending,
        'queues' => $sizes,
    ], $maxPending < 1000 ? 200 : 503);
});
```

---

## Artisan Commands

### `queue-monitor:purge`

Manually clear all monitoring data from Redis:

```bash
php artisan queue-monitor:purge
```

Use `--failed-only` to clear just the failed jobs list:

```bash
php artisan queue-monitor:purge --failed-only
```

### `queue-monitor:snapshot`

Take a point-in-time snapshot and store it in the database (requires `snapshot.enabled = true` and migrations):

```bash
php artisan queue-monitor:snapshot
```

To run automatically, add to your scheduler in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('queue-monitor:snapshot')->everyFiveMinutes();
```

### `queue-monitor:stats`

Print current queue stats to the terminal:

```bash
php artisan queue-monitor:stats
```

Output:

```
Queue Monitor — 2025-03-31 14:22:00
┌──────────┬─────────┬─────────┬──────────┐
│ Queue    │ Pending │ Delayed │ Reserved │
├──────────┼─────────┼─────────┼──────────┤
│ default  │      42 │      18 │        3 │
│ high     │      87 │       5 │        4 │
│ emails   │      14 │       2 │        0 │
└──────────┴─────────┴─────────┴──────────┘
Running: 7 jobs | Failed (1hr): 3 | Throughput: 47 jobs/min
```

---

## Redis Memory Breakdown

Here's what the package actually stores, with typical sizes for a system processing ~3,000 jobs per hour:

| Key pattern | Type | Count | Size |
|---|---|---|---|
| `queue-monitor:running:{id}` | Hash | ~7 active | ~1.4 KB |
| `queue-monitor:throughput:{queue}:{minute}` | String | ~180 keys (3 queues × 60 min) | ~2.8 KB |
| `queue-monitor:failed` | Sorted Set | up to 1,000 entries | ~400 KB |
| **Total** | | | **~0.4 MB** |

Even under heavy load (50,000 jobs/hour, 10 queues), the total stays under **5 MB**. Compare to Horizon's 20–100 MB for the same workload.

---

## Events

The package dispatches its own events that you can listen to for custom integrations:

```php
use YourVendor\QueueMonitor\Events\QueueBacklogDetected;
use YourVendor\QueueMonitor\Events\JobTookTooLong;
use YourVendor\QueueMonitor\Events\FailureRateExceeded;

// In EventServiceProvider or a listener
Event::listen(QueueBacklogDetected::class, function ($event) {
    // $event->queue — the queue name
    // $event->size — current pending count
    // $event->threshold — configured threshold
    Log::warning("Queue {$event->queue} has {$event->size} pending jobs");
});
```

---

## Notifications (Optional)

Enable alerts for queue health issues by adding notification channels in the config:

```php
'alerts' => [
    'backlog_threshold' => 500,       // Notify when any queue exceeds this
    'failure_rate_threshold' => 5.0,  // Notify when failure rate exceeds 5%
    'job_duration_threshold' => 300,  // Notify when a job runs longer than 5 min
    'channels' => ['slack', 'mail'],
    'slack_webhook' => env('QUEUE_MONITOR_SLACK_WEBHOOK'),
    'mail_to' => env('QUEUE_MONITOR_MAIL_TO'),
],
```

---

## Troubleshooting

### Dashboard shows no data

1. Verify your queue driver is Redis: `QUEUE_CONNECTION=redis` in `.env`
2. Verify monitoring is enabled: `QUEUE_MONITOR_ENABLED=true` in `.env`
3. Make sure workers are running: `php artisan queue:work`
4. Check that the event listeners are registered: `php artisan event:list | grep QueueMonitor`

### Queue sizes show but running/failed don't

The queue sizes are read directly from Redis (no listeners needed). Running and failed job tracking requires the event listeners to fire, which only happens when workers process jobs. Make sure your workers are using the Redis connection.

### Redis memory seems high

Check your retention settings. Lower `throughput_minutes` or `failed_max_entries`:

```php
'retention' => [
    'throughput_minutes' => 30,   // Reduce from 60 to 30
    'failed_max_entries' => 100,  // Reduce from 1000 to 100
],
```

Then purge existing data:

```bash
php artisan queue-monitor:purge
```

### Dashboard is slow

Increase the polling interval:

```php
'dashboard' => [
    'polling_interval' => 10,  // Poll every 10 seconds instead of 3
],
```

---

## Upgrading

### From 1.x to 2.x

No breaking changes planned yet. This section will be updated when needed.

---

## Contributing

Contributions are welcome. Please open an issue first to discuss what you'd like to change.

```bash
git clone https://github.com/yourvendor/laravel-queue-monitor.git
cd laravel-queue-monitor
composer install
php vendor/bin/phpunit
```

---

## License

MIT License. See [LICENSE](LICENSE) for details.
