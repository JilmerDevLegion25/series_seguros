<?php

namespace App\Actions\Notifications;

use App\Models\Notification;
use App\Services\Clock\Clock;

final readonly class MarkNotificationReadAction
{
    public function __construct(
        private Clock $clock,
    ) {}

    public function execute(Notification $notification): Notification
    {
        if ($notification->read_at !== null) {
            return $notification;
        }

        $notification->forceFill([
            'read_at' => $this->clock->now(),
        ])->save();

        return $notification;
    }
}
