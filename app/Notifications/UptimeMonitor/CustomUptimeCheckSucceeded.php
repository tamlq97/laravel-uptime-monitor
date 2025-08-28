<?php

namespace App\Notifications\UptimeMonitor;

use Carbon\Carbon;
use NotificationChannels\Telegram\TelegramMessage;
use Spatie\UptimeMonitor\Notifications\Notifications\UptimeCheckSucceeded;

class CustomUptimeCheckSucceeded extends UptimeCheckSucceeded
{
    public function toTelegram($notifiable)
    {
        return TelegramMessage::create()
            ->content("✅ *Website Successful*\n\n")
            ->line("🌐 **URL:** {$this->getMonitor()->url}")
            ->line($this->getMessageText())
            ->line($this->getLocationDescription())
            ->line("📍 **Location:** {$this->getLocationDescription()}")
            ->line("🕐 **Successful at:** " . Carbon::now()->format('Y-m-d H:i:s'));
    }
}
