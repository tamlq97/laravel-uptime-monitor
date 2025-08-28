<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\UptimeMonitor\Models\Monitor as ModelsMonitor;

class Monitor extends ModelsMonitor
{
    public static function booted()
    {
        parent::booted();
        static::creating(function ($monitor) {
            $maxPerMinute = config('monitor.max_monitors_per_minute');
            $total = self::query()->count();
            $bucketCount = max(1, ceil($total / $maxPerMinute));

            if ($monitor->uptime_check_interval_in_minutes === 1) {
                $monitor->check_bucket = -1; // bucket đặc biệt
            } else {
                $monitor->check_bucket = $total % $bucketCount;
            }
        });
    }
}
