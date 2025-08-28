<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateMonitorsCommand extends Command
{
    protected $signature = 'monitors:create {count=1000 : Number of monitors to create}';
    protected $description = 'Create multiple monitor records for testing';

    public function handle()
    {
        $count = (int) $this->argument('count');

        $this->info("Creating {$count} monitors...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $domains = [
            'google.com',
            'facebook.com',
            'youtube.com',
            'amazon.com',
            'wikipedia.org',
            'twitter.com',
            'instagram.com',
            'linkedin.com',
            'reddit.com',
            'netflix.com',
            'github.com',
            'stackoverflow.com',
            'apple.com',
            'microsoft.com',
            'adobe.com'
        ];

        $protocols = ['https', 'http'];
        $paths = ['', '/api', '/health', '/status', '/ping', '/check'];

        DB::transaction(function () use ($count, $bar, $domains, $protocols, $paths) {
            for ($i = 1; $i <= $count; $i++) {
                $domain = $domains[array_rand($domains)];
                $protocol = $protocols[array_rand($protocols)];
                $path = $paths[array_rand($paths)];
                $subdomain = 'test' . $i;

                $url = $protocol . '://' . $subdomain . '.' . $domain . $path;

                Monitor::create([
                    'url' => $url,
                    'uptime_check_enabled' => true,
                    'look_for_string' => '',
                    'uptime_check_interval_in_minutes' => rand(1, 60),
                    'uptime_status' => 'not_yet_checked',
                    'uptime_check_failure_reason' => '',
                    'uptime_check_times_failed_in_a_row' => 0,
                    'uptime_status_last_change_date' => now(),
                    'uptime_last_check_date' => null,
                    'uptime_check_failure_reason' => null,
                    'certificate_check_enabled' => $protocol === 'https',
                    'certificate_status' => 'not_yet_checked',
                    'certificate_expiration_date' => null,
                    'certificate_issuer' => null,
                    'certificate_check_failure_reason' => "",
                ]);

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Successfully created {$count} monitors!");

        return Command::SUCCESS;
    }
}
