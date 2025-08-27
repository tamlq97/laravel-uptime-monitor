<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\UptimeMonitor\Models\Monitor;

class Heartbeat extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitor_id',
        'status',
        'response_time',
        'status_code',
        'error_message',
        'response_headers',
        'response_body',
        'check_method',
        'checked_at',
    ];

    protected $casts = [
        'response_headers' => 'array',
        'checked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the monitor that owns the heartbeat.
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    /**
     * Scope to get heartbeats for a specific monitor.
     */
    public function scopeForMonitor($query, $monitorId)
    {
        return $query->where('monitor_id', $monitorId);
    }

    /**
     * Scope to get heartbeats within a date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('checked_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get only successful heartbeats.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'up');
    }

    /**
     * Scope to get only failed heartbeats.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'down');
    }

    /**
     * Get the latest heartbeats ordered by checked_at.
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('checked_at', 'desc');
    }
}