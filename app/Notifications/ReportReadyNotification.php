<?php

namespace App\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $reportTitle,
        public readonly string $filename,
        public readonly string $downloadUrl,
        public readonly CarbonImmutable $expiresAt,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_type' => 'report_ready',
            'type' => 'report.ready',
            'category' => 'reports',
            'severity' => 'info',
            'title' => __('PDF report ready: :title', ['title' => $this->reportTitle]),
            'message' => __('Your report (:filename) is ready to download.', ['filename' => $this->filename]),
            'url' => $this->downloadUrl,
            'filename' => $this->filename,
            'expires_at' => $this->expiresAt->toIso8601String(),
        ];
    }
}
