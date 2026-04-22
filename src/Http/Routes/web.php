<?php

use DhavalPtel\QueueMonitor\Http\Controllers\ApiController;
use DhavalPtel\QueueMonitor\Http\Controllers\DashboardController;
use DhavalPtel\QueueMonitor\Http\Middleware\Authorize;
use Illuminate\Support\Facades\Route;

$path = config('queue-monitor.dashboard.path', 'queue-monitor');
$middleware = config('queue-monitor.dashboard.middleware', ['web', 'auth']);

Route::group([
    'prefix' => $path,
    'middleware' => array_merge($middleware, [Authorize::class]),
], function () {
    // Dashboard view
    Route::get('/', [DashboardController::class, 'index'])->name('queue-monitor.dashboard');

    // JSON API endpoints
    Route::prefix('api')->group(function () {
        Route::get('/queues', [ApiController::class, 'queues'])->name('queue-monitor.api.queues');
        Route::get('/running', [ApiController::class, 'running'])->name('queue-monitor.api.running');
        Route::get('/failed', [ApiController::class, 'failed'])->name('queue-monitor.api.failed');
        Route::get('/throughput', [ApiController::class, 'throughput'])->name('queue-monitor.api.throughput');
        Route::get('/stats', [ApiController::class, 'stats'])->name('queue-monitor.api.stats');
        Route::get('/health', [ApiController::class, 'health'])->name('queue-monitor.api.health');
    });
});
