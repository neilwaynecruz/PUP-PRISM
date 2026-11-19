<?php

namespace App\Services\Operations;

use App\Models\EmailDelivery;
use App\Models\User;
use Closure;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class EmailDeliveryService
{
    public function enabled(): bool
    {
        return config('mail.default') === 'resend';
    }

    public ?EmailDelivery $current = null;

    public bool $permanentFailure = false;

    public int $retryDelay = 60;

    public string $failureCategory = 'temporary_or_unconfirmed';

    public function queued(JobQueued $event): void
    {
        if (! $this->enabled() || ! $event->job instanceof SendQueuedNotifications || ! in_array('mail', $event->job->channels ?? [], true)) {
            return;
        }

        foreach ($event->job->notifiables as $notifiable) {
            try {
                $this->record($notifiable, $event->job->notification);
            } catch (Throwable) {
                report(new RuntimeException('Email was queued, but delivery tracking could not be initialized.'));
            }
        }
    }

    public function record(object $notifiable, Notification $notification): EmailDelivery
    {
        $notification->id ??= (string) Str::uuid();
        $recipient = $notifiable->routeNotificationFor('mail', $notification);
        $identity = hash('sha256', $notification->id.'|'.json_encode($recipient, JSON_THROW_ON_ERROR));

        return EmailDelivery::query()->firstOrCreate(['id' => $identity], [
            'user_id' => $notifiable instanceof User ? $notifiable->getKey() : null,
            'notification_type' => $notification::class,
            'expires_at' => now()->setTimestamp($notification->mailDeadline ?? now()->addHours(23)->getTimestamp()),
        ]);
    }

    public function skip(object $notifiable, Notification $notification, string $status): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $record = $this->record($notifiable, $notification);
        EmailDelivery::query()->whereKey($record->id)->where('status', '!=', 'accepted')
            ->update(['status' => $status, 'request_payload' => null, 'next_attempt_at' => null]);

        return false;
    }

    public function send(object $notifiable, Notification $notification, Closure $send): ?SentMessage
    {
        $this->permanentFailure = false;
        $this->retryDelay = 60;
        $this->failureCategory = 'temporary_or_unconfirmed';
        $record = $this->record($notifiable, $notification);

        $result = Cache::lock('email-delivery:'.$record->id, 60)->get(function () use ($record, $send): ?SentMessage {
            $record->refresh();
            if (in_array($record->status, ['accepted', 'cancelled', 'failed', 'expired', 'superseded'], true)) {
                return null;
            }

            if ($record->expires_at->isPast()) {
                $record->update(['status' => 'expired', 'request_payload' => null, 'next_attempt_at' => null]);

                return null;
            }

            $record->update(['status' => 'processing', 'attempts' => $record->attempts + 1, 'next_attempt_at' => null]);
            $this->current = $record;

            try {
                $message = $send();
                if ($message === null) {
                    $record->update(['status' => 'cancelled', 'request_payload' => null]);

                    return null;
                }

                $providerId = $message->getOriginalMessage()->getHeaders()->get('X-Resend-Email-ID')?->getBodyAsString();
                if (! $providerId) {
                    $this->permanentFailure = true;
                    $this->failureCategory = 'missing_provider_evidence';
                    throw new EmailDeliveryException('No Resend acceptance evidence was returned.');
                }
                $record->update([
                    'status' => 'accepted',
                    'provider_id' => $providerId,
                    'request_payload' => null,
                    'failure_category' => null,
                ]);

                return $message;
            } catch (Throwable $exception) {
                if (! $exception instanceof TransportExceptionInterface && ! $exception instanceof EmailDeliveryException) {
                    EmailDelivery::query()->whereKey($record->id)->where('status', '!=', 'accepted')
                        ->update(['status' => 'failed', 'failure_category' => 'application_error', 'request_payload' => null]);

                    throw $exception;
                }

                $this->retryDelay = max($this->retryDelay, [60, 300, 900, 1800][$record->attempts - 1] ?? 1800);
                $exhausted = $record->attempts >= 5 || now()->addSeconds($this->retryDelay)->greaterThanOrEqualTo($record->expires_at);
                $this->permanentFailure = $this->permanentFailure || $exhausted;
                EmailDelivery::query()->whereKey($record->id)->where('status', '!=', 'accepted')->update([
                    'status' => $this->permanentFailure ? 'failed' : 'retry_scheduled',
                    'failure_category' => $exhausted ? 'attempts_or_deadline_exhausted' : $this->failureCategory,
                    'next_attempt_at' => $this->permanentFailure ? null : now()->addSeconds($this->retryDelay),
                ]);
                if ($this->permanentFailure) {
                    $record->update(['request_payload' => null]);
                }

                throw new EmailDeliveryException('Email delivery failed. Review restricted delivery status; no message content is logged.');
            } finally {
                $this->current = null;
            }
        });

        if ($result === false) {
            throw new EmailDeliveryException('Email delivery is already being processed.');
        }

        return $result;
    }
}
