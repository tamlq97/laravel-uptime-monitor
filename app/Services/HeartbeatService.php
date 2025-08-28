<?php

namespace App\Services;

use App\Models\Heartbeat;
use App\Models\Monitor;
use Illuminate\Support\Facades\Log;

class HeartbeatService
{
    /**
     * Record a heartbeat for a monitor.
     */
    public function recordHeartbeat(
        Monitor $monitor,
        string $status,
        ?int $responseTime = null,
        ?int $statusCode = null,
        ?string $errorMessage = null,
        ?array $responseHeaders = null,
        ?string $responseBody = null,
        string $checkMethod = 'GET'
    ): Heartbeat {
        try {
            return Heartbeat::create([
                'monitor_id' => $monitor->id,
                'status' => $status,
                'response_time' => $responseTime,
                'status_code' => $statusCode,
                'error_message' => $errorMessage,
                'response_headers' => $responseHeaders,
                'response_body' => $this->truncateResponseBody($responseBody),
                'check_method' => $checkMethod,
                'checked_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to record heartbeat', [
                'monitor_id' => $monitor->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get heartbeat statistics for a monitor.
     */
    public function getStatistics(Monitor $monitor, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $heartbeats = Heartbeat::forMonitor($monitor->id)
            ->betweenDates($startDate, now())
            ->get();

        $total = $heartbeats->count();
        $successful = $heartbeats->where('status', 'up')->count();
        $failed = $heartbeats->where('status', 'down')->count();

        $uptime = $total > 0 ? ($successful / $total) * 100 : 0;
        $avgResponseTime = $heartbeats->where('response_time', '>', 0)->avg('response_time');

        return [
            'total_checks' => $total,
            'successful_checks' => $successful,
            'failed_checks' => $failed,
            'uptime_percentage' => round($uptime, 2),
            'average_response_time' => $avgResponseTime ? round($avgResponseTime, 2) : null,
            'period_days' => $days,
        ];
    }

    /**
     * Clean up old heartbeats to prevent database bloat.
     */
    public function cleanupOldHeartbeats(int $daysToKeep = 90): int
    {
        $cutoffDate = now()->subDays($daysToKeep);

        return Heartbeat::where('checked_at', '<', $cutoffDate)->delete();
    }

    /**
     * Truncate response body to prevent database bloat.
     */
    private function truncateResponseBody(?string $responseBody, int $maxLength = 1000): ?string
    {
        if (!$responseBody) {
            return null;
        }

        return strlen($responseBody) > $maxLength
            ? substr($responseBody, 0, $maxLength) . '...'
            : $responseBody;
    }
}
