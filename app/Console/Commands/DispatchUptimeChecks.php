<?php

namespace App\Console\Commands;

use App\Jobs\CheckUptimeJob;
use Illuminate\Console\Command;
use Spatie\UptimeMonitor\MonitorRepository;

class DispatchUptimeChecks extends Command
{
    protected $signature = 'monitor:dispatch-checks';
    protected $description = 'Dispatches a job for each enabled monitor.';

    public function handle(): int
    {
        $monitors = MonitorRepository::getForUptimeCheck();

        $monitors->each(fn($monitor) => dispatch(new CheckUptimeJob($monitor)));

        $this->info('All uptime check jobs have been dispatched.');

        return 0;
    }
}
