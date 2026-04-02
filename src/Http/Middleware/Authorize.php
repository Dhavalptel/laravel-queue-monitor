<?php

namespace DhavalPtel\QueueMonitor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $gate = config('queue-monitor.gate', 'viewQueueMonitor');

        if (Gate::has($gate) && Gate::denies($gate)) {
            abort(403);
        }

        return $next($request);
    }
}
