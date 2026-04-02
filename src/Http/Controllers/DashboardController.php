<?php

namespace DhavalPtel\QueueMonitor\Http\Controllers;

use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    /**
     * Show the queue monitor dashboard.
     */
    public function index()
    {
        return view('queue-monitor::dashboard', [
            'pollingInterval' => config('queue-monitor.dashboard.polling_interval', 3),
            'path' => config('queue-monitor.dashboard.path', 'queue-monitor'),
        ]);
    }
}
