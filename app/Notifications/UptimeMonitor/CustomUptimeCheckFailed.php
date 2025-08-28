<?php

namespace App\Notifications\UptimeMonitor;

use Carbon\Carbon;
use NotificationChannels\Telegram\TelegramMessage;
use Spatie\UptimeMonitor\Notifications\Notifications\UptimeCheckFailed;

class CustomUptimeCheckFailed extends UptimeCheckFailed
{
    public function toTelegram($notifiable)
    {
        return TelegramMessage::create()
            ->content("🚨 *Website Down Alert*\n\n")
            ->line("🌐 **URL:** {$this->getMonitor()->url}")
            ->line("❌ **Status:** Website seems down")
            ->line("🔍 **Failure Reason:** {$this->getMonitor()->uptime_check_failure_reason}")
            ->line("📍 **Location:** {$this->getLocationDescription()}")
            ->line("🕐 **Failed at:** " . Carbon::now()->format('Y-m-d H:i:s'));
    }
}
