<?php

namespace App\Services\Operations;

use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;

class ReliableMailChannel extends MailChannel
{
    public function send($notifiable, Notification $notification): ?SentMessage
    {
        if (! app(EmailDeliveryService::class)->enabled()) {
            return parent::send($notifiable, $notification);
        }

        return app(EmailDeliveryService::class)->send(
            $notifiable,
            $notification,
            fn () => parent::send($notifiable, $notification),
        );
    }
}
