<?php

namespace App\Notifications\Concerns;

use App\Services\Operations\NotificationMailMiddleware;
use Carbon\CarbonImmutable;

trait HasReliableMailDelivery
{
    public int $mailDeadline;

    public int $timeout = 30;

    public function initializeMailDelivery(): void
    {
        $this->mailDeadline = now()->addHours(23)->getTimestamp();
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    /** @return array<string, string> */
    public function viaQueues(): array
    {
        return ['mail' => $this->mailQueue(), 'database' => 'notifications', 'broadcast' => 'broadcast'];
    }

    protected function mailQueue(): string
    {
        return 'mail';
    }

    /** @return list<NotificationMailMiddleware> */
    public function middleware(object $notifiable, string $channel): array
    {
        return $channel === 'mail' ? [new NotificationMailMiddleware] : [];
    }

    public function retryUntil(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp($this->mailDeadline);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }
}
