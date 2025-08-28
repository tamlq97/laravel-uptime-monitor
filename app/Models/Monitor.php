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
            // Ví dụ chia 10 bucket
            $bucketCount = 10;

            // Nếu interval = 1 phút thì ép bucket = -1 (để nhận diện riêng)
            if ($monitor->uptime_check_interval_in_minutes === 1) {
                $monitor->check_bucket = -1;
                return;
            }

            // Round robin dựa trên tổng số record
            $bucket = ($monitor->newQuery()->max('id') + 1) % $bucketCount;
            $monitor->check_bucket = $bucket;
        });
    }
}
