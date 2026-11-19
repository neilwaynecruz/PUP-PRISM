<?php

namespace App\Services\Operations;

use Illuminate\Notifications\SendQueuedNotifications;
use RuntimeException;
use Throwable;

class NotificationMailMiddleware
{
    public function handle(SendQueuedNotifications $job, callable $next): void
    {
        try {
            $next($job);
        } catch (EmailDeliveryException) {
            $delivery = app(EmailDeliveryService::class);
            if ($delivery->permanentFailure || $job->attempts() >= 5) {
                $job->fail(new RuntimeException('Email could not be sent. Review Operations Health and delivery metadata.'));

                return;
            }

            $job->release($delivery->retryDelay);
        } catch (Throwable $exception) {
            $job->fail($exception);
        }
    }
}
