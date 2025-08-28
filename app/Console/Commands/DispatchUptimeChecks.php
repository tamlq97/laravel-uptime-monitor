<?php

namespace App\Console\Commands;

use App\Jobs\CheckUptimeJob;
use Illuminate\Console\Command;
use Spatie\UptimeMonitor\Models\Enums\UptimeStatus;
use Spatie\UptimeMonitor\Models\Monitor;
use Spatie\UptimeMonitor\MonitorRepository;

class DispatchUptimeChecks extends Command
{
    protected $signature = 'monitor:dispatch-checks';
    protected $description = 'Dispatches a job for each enabled monitor.';

    public function handle(): int
    {
        $now = now();
        Monitor::where('uptime_check_enabled', true)
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
            ->chunk(1000, function ($monitors) {
                foreach ($monitors as $monitor) {
                    dispatch(new CheckUptimeJob($monitor));
                }
            });

        $this->info('All uptime check jobs have been dispatched.');

        return 0;
    }
}
