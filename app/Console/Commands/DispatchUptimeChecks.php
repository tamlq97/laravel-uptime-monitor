<?php

namespace App\Console\Commands;

use App\Jobs\CheckMonitorBatchJob;
use App\Jobs\CheckUptimeJob;
use Illuminate\Console\Command;
use Spatie\UptimeMonitor\Models\Enums\UptimeStatus;
use Spatie\UptimeMonitor\Models\Monitor;
use Spatie\UptimeMonitor\MonitorCollection;
use Spatie\UptimeMonitor\MonitorRepository;

class DispatchUptimeChecks extends Command
{
    protected $signature = 'monitor:dispatch-checks {--batch=100} {--buckets=10}';
    protected $description = 'Dispatches a job for each enabled monitor.';

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $bucketCount = (int) $this->option('buckets');
        // Xác định bucket hiện tại dựa vào phút hiện tại
        $currentBucket = now()->minute % $bucketCount;

        $this->info("Dispatching monitors for bucket {$currentBucket} and always bucket -1");

        $now = now();
        // 1️⃣ Luôn chạy monitor interval = 1 phút (bucket = -1)
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
                dispatch(new CheckMonitorBatchJob($monitors->pluck('id')->toArray()));
            });

        // 2️⃣ Chỉ chạy bucket tới lượt
        Monitor::query()
            ->where('uptime_check_enabled', true)
            ->where('check_bucket', $currentBucket)
            ->where(function ($query) {
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
                dispatch(new CheckMonitorBatchJob($monitors->pluck('id')->toArray()));
            });

        $this->info('All uptime check jobs have been dispatched.');

        return 0;
    }
}
