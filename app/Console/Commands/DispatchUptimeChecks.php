<?php

namespace App\Console\Commands;

use App\Jobs\CheckMonitorBatchJob;
use App\Models\Monitor;
use Illuminate\Console\Command;
use Spatie\UptimeMonitor\Models\Enums\UptimeStatus;

class DispatchUptimeChecks extends Command
{
    protected $signature = 'monitor:dispatch-checks {--batch=} {--maxPerMinute=}';
    protected $description = 'Dispatch monitor checks with dynamic buckets';

    public function handle(): int
    {
        $batchSize      = $this->option('batch') ?? config('monitor.batch_size', 100);
        $maxPerMinute   = $this->option('maxPerMinute') ?? config('monitor.max_monitors_per_minute', 2000);

        $totalMonitors  = Monitor::where('uptime_check_enabled', true)->count();

        // Tính số bucket động
        $bucketCount = max(1, ceil($totalMonitors / $maxPerMinute));
        $currentBucket = now()->minute % $bucketCount;

        $this->info("Total: {$totalMonitors}, Buckets: {$bucketCount}, Current bucket: {$currentBucket}");

        $now = now();

        /**
         * 1️⃣ Monitor ưu tiên (DOWN + chưa từng check)
         */
        Monitor::query()
            ->where('uptime_check_enabled', true)
            ->where(function ($query) use ($now) {
                // Case 1: Monitor has never been checked
                $query->whereNull('uptime_last_check_date')
                    // Case 2: Monitor is currently down
                    ->orWhere('uptime_status', UptimeStatus::DOWN)
                    // Case 3: Monitor has never been checked before (status is 'not yet checked')
                    ->orWhere('uptime_status', UptimeStatus::NOT_YET_CHECKED);
            })
            ->orderBy('id')
            ->chunk($batchSize, function ($monitors) {
                $ids = $monitors->pluck('id')->toArray();
                dispatch((new CheckMonitorBatchJob($ids))->onQueue('monitor-priority'));
            });

        // 2️⃣ Luôn chạy monitor interval = 1 phút (bucket = -1)
        Monitor::query()
            ->where('uptime_check_enabled', true)
            ->where('check_bucket', -1)
            ->where(function ($query) use ($now) {
                // Case 1: Monitor has never been checked
                $query->whereNull('uptime_last_check_date')
                    // Case 2: Monitor is currently down
                    ->orWhere('uptime_status', UptimeStatus::DOWN)
                    // Case 3: Monitor has never been checked before (status is 'not yet checked')
                    ->orWhere('uptime_status', UptimeStatus::NOT_YET_CHECKED)
                    // Case 4: Last check was more than the interval ago
                    ->orWhereRaw('ADDDATE(uptime_last_check_date, INTERVAL uptime_check_interval_in_minutes MINUTE) <= ?', [$now]);
            })
            ->chunk($batchSize, function ($monitors) {
                dispatch(new CheckMonitorBatchJob($monitors->pluck('id')->toArray()))->onQueue('monitor-every-minute');
            });

        /**
         * 3️⃣ Monitor định kỳ (bucket-based, interval > 1 phút)
         */
        Monitor::query()
            ->where('uptime_check_enabled', true)
            ->where('check_bucket', $currentBucket)
            ->where('uptime_status', '!=', 'DOWN')
            ->where('uptime_check_interval_in_minutes', '>', 1)
            ->whereNotNull('uptime_last_check_date')
            ->whereRaw(
                'ADDDATE(uptime_last_check_date, INTERVAL uptime_check_interval_in_minutes MINUTE) <= ?',
                [$now]
            )
            ->chunk($batchSize, function ($monitors) {
                dispatch(new CheckMonitorBatchJob($monitors->pluck('id')->toArray())->onQueue('monitor-regular'));
            });

        $this->info('All uptime check jobs have been dispatched.');

        return 0;
    }
}
