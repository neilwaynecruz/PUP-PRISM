<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class DailyNotificationDigest extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  Collection<int, DatabaseNotification>  $entries
     */
    public function __construct(public Collection $entries)
    {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('Your daily notification digest'))
            ->line(__('Here is a summary of notifications from the last day:'));

        foreach ($this->entries as $entry) {
            $data = is_array($entry->data) ? $entry->data : [];
            $title = (string) ($data['title'] ?? __('Notification'));
            $body = (string) ($data['message'] ?? '');

            $message->line("{$title} — {$body}");
        }

        return $message
            ->action(__('Open notification center'), route('dashboard', absolute: false))
            ->line(__('You can manage delivery channels and digest frequency in Settings.'));
    }
}
