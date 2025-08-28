<?php

namespace App\Notifications\UptimeMonitor;

use Carbon\Carbon;
use NotificationChannels\Telegram\TelegramMessage;
use Spatie\UptimeMonitor\Notifications\Notifications\UptimeCheckRecovered;

class CustomUptimeCheckRecovered extends UptimeCheckRecovered
{
    public function toTelegram($notifiable)
    {
        return TelegramMessage::create()
            ->to('5035815143')
            ->content("✅ *Website Recovered*\n\n")
            ->line("🌐 **URL:** {$this->getMonitor()->url}")
            ->line("⏱️ **Downtime:** {$this->event->downtimePeriod->duration()}")
            ->line("📍 **Location:** {$this->getLocationDescription()}")
            ->line("🕐 **Recovered at:** " . Carbon::now()->format('Y-m-d H:i:s'));
    }
}
