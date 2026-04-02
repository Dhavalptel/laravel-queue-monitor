<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Queue Monitor</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            --bg-primary: #0a0e17;
            --bg-secondary: #111827;
            --bg-card: #151d2e;
            --bg-card-hover: #1a2438;
            --border: #1e2d45;
            --border-subtle: #162033;
            --text-primary: #e2e8f0;
            --text-secondary: #8494a7;
            --text-muted: #4a5e78;
            --accent-green: #10b981;
            --accent-green-dim: rgba(16, 185, 129, 0.12);
            --accent-green-glow: rgba(16, 185, 129, 0.25);
            --accent-red: #ef4444;
            --accent-red-dim: rgba(239, 68, 68, 0.12);
            --accent-amber: #f59e0b;
            --accent-amber-dim: rgba(245, 158, 11, 0.12);
            --accent-blue: #3b82f6;
            --accent-blue-dim: rgba(59, 130, 246, 0.12);
            --accent-purple: #8b5cf6;
            --accent-purple-dim: rgba(139, 92, 246, 0.12);
            --mono: 'JetBrains Mono', monospace;
            --sans: 'DM Sans', sans-serif;
            --radius: 10px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg-primary); color: var(--text-primary); font-family: var(--sans); min-height: 100vh; }
        body::before { content: ''; position: fixed; inset: 0; background-image: linear-gradient(rgba(30,45,69,0.3) 1px, transparent 1px), linear-gradient(90deg, rgba(30,45,69,0.3) 1px, transparent 1px); background-size: 60px 60px; pointer-events: none; z-index: 0; }
        .app { position: relative; z-index: 1; }

        /* Header */
        .header { display: flex; align-items: center; justify-content: space-between; padding: 20px 32px; border-bottom: 1px solid var(--border-subtle); backdrop-filter: blur(12px); background: rgba(10,14,23,0.8); position: sticky; top: 0; z-index: 100; }
        .header-left { display: flex; align-items: center; gap: 14px; }
        .logo { width: 36px; height: 36px; background: linear-gradient(135deg, var(--accent-green), #059669); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-family: var(--mono); font-weight: 700; font-size: 16px; color: #fff; box-shadow: 0 0 20px var(--accent-green-glow); }
        .header-title { font-family: var(--mono); font-weight: 600; font-size: 16px; letter-spacing: -0.02em; }
        .header-subtitle { font-size: 12px; color: var(--text-muted); font-family: var(--mono); margin-top: 1px; }
        .header-right { display: flex; align-items: center; gap: 16px; }
        .connection-badge { display: flex; align-items: center; gap: 6px; font-family: var(--mono); font-size: 11px; color: var(--accent-green); background: var(--accent-green-dim); padding: 5px 12px; border-radius: 20px; border: 1px solid rgba(16,185,129,0.2); }
        .connection-dot { width: 6px; height: 6px; background: var(--accent-green); border-radius: 50%; animation: pulse-dot 2s ease-in-out infinite; }
        @keyframes pulse-dot { 0%,100% { opacity:1 } 50% { opacity:0.5 } }
        .env-badge { font-family: var(--mono); font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--accent-amber); background: var(--accent-amber-dim); padding: 4px 10px; border-radius: 4px; border: 1px solid rgba(245,158,11,0.2); }

        /* Main */
        .main { padding: 24px 32px; max-width: 1440px; margin: 0 auto; }

        /* Stats Row */
        .stats-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; position: relative; overflow: hidden; transition: all 0.2s ease; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; }
        .stat-card.green::before { background: linear-gradient(90deg, var(--accent-green), transparent); }
        .stat-card.red::before { background: linear-gradient(90deg, var(--accent-red), transparent); }
        .stat-card.blue::before { background: linear-gradient(90deg, var(--accent-blue), transparent); }
        .stat-card.amber::before { background: linear-gradient(90deg, var(--accent-amber), transparent); }
        .stat-card.purple::before { background: linear-gradient(90deg, var(--accent-purple), transparent); }
        .stat-card:hover { border-color: #2a3f5f; }
        .stat-label { font-family: var(--mono); font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 10px; }
        .stat-value { font-family: var(--mono); font-size: 32px; font-weight: 700; letter-spacing: -0.03em; line-height: 1; }
        .stat-value.green { color: var(--accent-green); }
        .stat-value.red { color: var(--accent-red); }
        .stat-value.blue { color: var(--accent-blue); }
        .stat-value.amber { color: var(--accent-amber); }
        .stat-value.purple { color: var(--accent-purple); }
        .stat-sub { margin-top: 8px; font-family: var(--mono); font-size: 11px; color: var(--text-muted); }

        /* Section */
        .section-title { font-family: var(--mono); font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: var(--border-subtle); }

        /* Queue Cards */
        .queues-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .queue-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; }
        .queue-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .queue-name { font-family: var(--mono); font-weight: 600; font-size: 14px; }
        .queue-status { font-family: var(--mono); font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; padding: 3px 8px; border-radius: 4px; }
        .queue-status.healthy { color: var(--accent-green); background: var(--accent-green-dim); border: 1px solid rgba(16,185,129,0.2); }
        .queue-status.backlog { color: var(--accent-amber); background: var(--accent-amber-dim); border: 1px solid rgba(245,158,11,0.2); }
        .queue-status.critical { color: var(--accent-red); background: var(--accent-red-dim); border: 1px solid rgba(239,68,68,0.2); }
        .queue-metrics { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .queue-metric-label { font-size: 10px; color: var(--text-muted); font-family: var(--mono); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; }
        .queue-metric-value { font-family: var(--mono); font-size: 18px; font-weight: 600; }

        /* Content Grid */
        .content-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 28px; }

        /* Chart */
        .chart-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; }
        .chart-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .chart-title { font-family: var(--mono); font-weight: 600; font-size: 13px; }
        .throughput-chart { height: 160px; display: flex; align-items: flex-end; gap: 2px; }
        .throughput-chart .bar { flex: 1; border-radius: 2px 2px 0 0; min-height: 2px; transition: height 0.3s ease; cursor: pointer; }
        .throughput-chart .bar:hover { filter: brightness(1.3); }

        /* Tables */
        .table-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
        .table-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--border); }
        .table-title { font-family: var(--mono); font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 8px; }
        .table-count { font-family: var(--mono); font-size: 10px; padding: 2px 8px; border-radius: 10px; }
        .table-count.green { background: var(--accent-green-dim); color: var(--accent-green); }
        .table-count.red { background: var(--accent-red-dim); color: var(--accent-red); }
        table { width: 100%; border-collapse: collapse; }
        thead th { font-family: var(--mono); font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); text-align: left; padding: 10px 20px; border-bottom: 1px solid var(--border-subtle); background: rgba(0,0,0,0.15); }
        tbody td { padding: 12px 20px; border-bottom: 1px solid var(--border-subtle); font-size: 13px; }
        tbody tr:hover { background: rgba(255,255,255,0.02); }
        tbody tr:last-child td { border-bottom: none; }
        .job-class { font-family: var(--mono); font-size: 12px; font-weight: 500; }
        .job-queue-tag { font-family: var(--mono); font-size: 10px; padding: 2px 8px; border-radius: 4px; display: inline-block; background: var(--accent-blue-dim); color: var(--accent-blue); }
        .job-duration { font-family: var(--mono); font-size: 12px; color: var(--text-secondary); }
        .job-time { font-family: var(--mono); font-size: 11px; color: var(--text-muted); }
        .running-indicator { width: 5px; height: 5px; background: var(--accent-green); border-radius: 50%; display: inline-block; animation: pulse-dot 1.5s ease-in-out infinite; margin-right: 4px; }
        .exception-text { font-family: var(--mono); font-size: 11px; color: var(--accent-red); max-width: 350px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* Footer */
        .footer { padding: 20px 32px; border-top: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; font-family: var(--mono); font-size: 11px; color: var(--text-muted); }

        /* Empty state */
        .empty-state { text-align: center; padding: 40px; color: var(--text-muted); font-family: var(--mono); font-size: 13px; }

        /* Responsive */
        @media (max-width: 1100px) { .stats-row { grid-template-columns: repeat(3, 1fr); } .content-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .stats-row { grid-template-columns: repeat(2, 1fr); } .main { padding: 16px; } .header { padding: 16px; } }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-primary); }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
    </style>
</head>
<body>
<div class="app" x-data="queueMonitor()" x-init="init()">
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <div class="logo">Q</div>
            <div>
                <div class="header-title">Queue Monitor</div>
                <div class="header-subtitle">laravel/queue-monitor</div>
            </div>
        </div>
        <div class="header-right">
            <span class="env-badge" x-text="'{{ app()->environment() }}'"></span>
            <div class="connection-badge">
                <span class="connection-dot"></span>
                Redis connected
            </div>
        </div>
    </header>

    <!-- Main -->
    <main class="main">
        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card green">
                <div class="stat-label">Jobs processed / hr</div>
                <div class="stat-value green" x-text="formatNumber(stats.processed_per_hour || 0)"></div>
                <div class="stat-sub" x-text="(stats.failure_rate || 0) + '% failure rate'"></div>
            </div>
            <div class="stat-card blue">
                <div class="stat-label">Total pending</div>
                <div class="stat-value blue" x-text="formatNumber(stats.total_pending || 0)"></div>
                <div class="stat-sub">across all queues</div>
            </div>
            <div class="stat-card amber">
                <div class="stat-label">Running now</div>
                <div class="stat-value amber" x-text="stats.running_count || 0"></div>
                <div class="stat-sub" x-text="queues.length + ' queues'"></div>
            </div>
            <div class="stat-card red">
                <div class="stat-label">Failed (last hr)</div>
                <div class="stat-value red" x-text="stats.failed_count || 0"></div>
                <div class="stat-sub" x-text="(stats.failure_rate || 0) + '% rate'"></div>
            </div>
            <div class="stat-card purple">
                <div class="stat-label">Avg. wait time</div>
                <div class="stat-value purple" x-text="(stats.avg_wait_seconds || 0) + 's'"></div>
                <div class="stat-sub">queue to process</div>
            </div>
        </div>

        <!-- Queue Cards -->
        <div class="section-title">Queues</div>
        <div class="queues-row">
            <template x-if="queues.length === 0">
                <div class="empty-state">No queues detected. Are workers running?</div>
            </template>
            <template x-for="queue in queues" :key="queue.name">
                <div class="queue-card">
                    <div class="queue-header">
                        <span class="queue-name" x-text="queue.name"></span>
                        <span class="queue-status" :class="queue.status" x-text="queue.status"></span>
                    </div>
                    <div class="queue-metrics">
                        <div>
                            <div class="queue-metric-label">Pending</div>
                            <div class="queue-metric-value" x-text="queue.pending"></div>
                        </div>
                        <div>
                            <div class="queue-metric-label">Delayed</div>
                            <div class="queue-metric-value" x-text="queue.delayed"></div>
                        </div>
                        <div>
                            <div class="queue-metric-label">Reserved</div>
                            <div class="queue-metric-value" x-text="queue.reserved"></div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Two-column: Throughput + Running -->
        <div class="content-grid">
            <!-- Throughput Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <span class="chart-title">Throughput (jobs/min)</span>
                </div>
                <div class="throughput-chart" x-ref="throughputChart">
                    <template x-for="(value, idx) in throughputBars" :key="idx">
                        <div class="bar"
                             :style="'height:' + barHeight(value) + 'px; background:' + barColor(value)"
                             :title="value + ' jobs/min'"></div>
                    </template>
                </div>
            </div>

            <!-- Running Jobs -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">
                        Running Jobs
                        <span class="table-count green" x-text="running.length"></span>
                    </div>
                </div>
                <template x-if="running.length === 0">
                    <div class="empty-state">No jobs currently running</div>
                </template>
                <template x-if="running.length > 0">
                    <table>
                        <thead><tr><th>Job</th><th>Queue</th><th>Duration</th></tr></thead>
                        <tbody>
                            <template x-for="job in running" :key="job.id">
                                <tr>
                                    <td><span class="job-class" x-text="shortClass(job.job)"></span></td>
                                    <td><span class="job-queue-tag" x-text="job.queue"></span></td>
                                    <td>
                                        <span class="running-indicator"></span>
                                        <span class="job-duration" x-text="job.duration_seconds + 's'"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </template>
            </div>
        </div>

        <!-- Failed Jobs -->
        <div class="table-card" style="margin-bottom: 28px;">
            <div class="table-header">
                <div class="table-title">
                    Recent Failures
                    <span class="table-count red" x-text="failed.length"></span>
                </div>
            </div>
            <template x-if="failed.length === 0">
                <div class="empty-state">No recent failures</div>
            </template>
            <template x-if="failed.length > 0">
                <table>
                    <thead><tr><th>Job</th><th>Queue</th><th>Exception</th><th>Attempts</th><th>Failed At</th></tr></thead>
                    <tbody>
                        <template x-for="(job, idx) in failed.slice(0, 20)" :key="idx">
                            <tr>
                                <td><span class="job-class" x-text="shortClass(job.job)"></span></td>
                                <td><span class="job-queue-tag" x-text="job.queue"></span></td>
                                <td><span class="exception-text" x-text="job.exception"></span></td>
                                <td><span class="job-duration" x-text="(job.attempts || 0) + ' / ' + (job.max_tries || '?')"></span></td>
                                <td><span class="job-time" x-text="timeAgo(job.failed_at)"></span></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </template>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <span>Polling every {{ $pollingInterval }}s</span>
        <span x-text="'Last updated: ' + lastUpdated"></span>
    </footer>
</div>

<script>
function queueMonitor() {
    return {
        queues: [],
        running: [],
        failed: [],
        stats: {},
        throughputBars: [],
        lastUpdated: 'loading...',
        polling: null,

        async init() {
            await this.fetchAll();
            this.polling = setInterval(() => this.fetchAll(), {{ $pollingInterval }} * 1000);
        },

        async fetchAll() {
            try {
                const base = '/{{ $path }}/api';
                const headers = {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                };

                const [queuesRes, runningRes, failedRes, statsRes, throughputRes] = await Promise.all([
                    fetch(base + '/queues', { headers }),
                    fetch(base + '/running', { headers }),
                    fetch(base + '/failed', { headers }),
                    fetch(base + '/stats', { headers }),
                    fetch(base + '/throughput?minutes=60', { headers }),
                ]);

                const queuesData = await queuesRes.json();
                const runningData = await runningRes.json();
                const failedData = await failedRes.json();
                const statsData = await statsRes.json();
                const throughputData = await throughputRes.json();

                this.queues = queuesData.queues || [];
                this.running = runningData.jobs || [];
                this.failed = failedData.jobs || [];
                this.stats = statsData;
                this.processThroughput(throughputData.queues || {});

                this.lastUpdated = 'just now';
            } catch (e) {
                console.error('Queue Monitor fetch error:', e);
                this.lastUpdated = 'error fetching data';
            }
        },

        processThroughput(queues) {
            // Sum all queues into a single timeline
            const merged = {};
            for (const [queueName, buckets] of Object.entries(queues)) {
                for (const [bucket, count] of Object.entries(buckets)) {
                    merged[bucket] = (merged[bucket] || 0) + count;
                }
            }
            // Take last 60 values
            const values = Object.values(merged);
            this.throughputBars = values.length > 60 ? values.slice(-60) : values;
        },

        barHeight(value) {
            const max = Math.max(...this.throughputBars, 1);
            return Math.max(2, (value / max) * 150);
        },

        barColor(value) {
            const max = Math.max(...this.throughputBars, 1);
            const intensity = value / max;
            if (intensity > 0.8) return 'linear-gradient(180deg, #10b981, #059669)';
            if (intensity > 0.5) return 'rgba(16,185,129,0.6)';
            return 'rgba(16,185,129,0.25)';
        },

        shortClass(fqcn) {
            if (!fqcn) return 'Unknown';
            const parts = fqcn.split('\\');
            return parts.length > 2
                ? parts.slice(-2).join('\\')
                : fqcn;
        },

        formatNumber(n) {
            return new Intl.NumberFormat().format(n);
        },

        timeAgo(timestamp) {
            if (!timestamp) return '';
            const seconds = Math.floor(Date.now() / 1000) - timestamp;
            if (seconds < 60) return seconds + 's ago';
            if (seconds < 3600) return Math.floor(seconds / 60) + ' min ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + ' hr ago';
            return Math.floor(seconds / 86400) + 'd ago';
        },

        destroy() {
            if (this.polling) clearInterval(this.polling);
        }
    };
}
</script>
</body>
</html>
