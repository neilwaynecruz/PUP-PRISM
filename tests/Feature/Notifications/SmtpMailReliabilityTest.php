<?php

use App\Models\EmailDelivery;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use App\Services\Operations\EmailDeliveryException;
use App\Services\Operations\EmailDeliveryService;
use App\Services\Operations\SystemHealthService;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test']);
});

test('SMTP acceptance is tracked without a Resend header and repeated jobs do not resubmit', function () {
    $user = User::factory()->unverified()->create();
    $notification = new QueuedVerifyEmail($user->email, $user);
    $email = (new Email)->from('sender@example.test')->to($user->email)->text('Synthetic message');
    $message = new SentMessage(new Symfony\Component\Mailer\SentMessage($email, Envelope::create($email)));
    $calls = 0;
    $send = function () use (&$calls, $message) {
        $calls++;

        return $message;
    };
    $service = app(EmailDeliveryService::class);
    expect($service->enabled())->toBeTrue();
    $service->send($user, $notification, $send);
    $service->send($user, $notification, $send);
    expect($calls)->toBe(1)->and(EmailDelivery::query()->sole()->status)->toBe('accepted')
        ->and(EmailDelivery::query()->sole()->provider_id)->toStartWith('smtp:');
    $check = collect(app(SystemHealthService::class)->report()['checks'])->firstWhere('key', 'email');
    expect(json_encode($check))->toContain('Accepted by transport (not proof of delivery)')->not->toContain('Synthetic message');
});

test('SMTP rejects permanent errors and retries only explicit temporary rejections', function (int $code, string $category, bool $permanent) {
    $user = User::factory()->unverified()->create();
    $service = app(EmailDeliveryService::class);
    expect(fn () => $service->send($user, new QueuedVerifyEmail($user->email, $user), fn () => throw new TransportException('Private SMTP details', $code)))
        ->toThrow(EmailDeliveryException::class, 'Email delivery failed.');
    expect(EmailDelivery::query()->sole()->status)->toBe($permanent ? 'failed' : 'retry_scheduled')
        ->and(EmailDelivery::query()->sole()->failure_category)->toBe($category)
        ->and($service->permanentFailure)->toBe($permanent);
})->with([[535, 'smtp_authentication', true], [550, 'smtp_rejected_request', true], [451, 'smtp_temporary_rejection', false], [0, 'smtp_acceptance_unconfirmed', true]]);

test('SMTP expired notifications do not send and queued notifications are tracked', function () {
    $user = User::factory()->unverified()->create();
    $notification = new QueuedVerifyEmail($user->email, $user);
    $this->travel(2)->hours();
    Notification::sendNow($user, $notification);
    expect(EmailDelivery::query()->sole()->status)->toBe('expired');
    $realQueue = app('queue')->connection('database');
    $job = new SendQueuedNotifications($user, new QueuedVerifyEmail($user->email, $user), ['mail']);
    $realQueue->push($job->beforeCommit(), '', 'security');
    expect(EmailDelivery::query()->where('status', 'queued')->count())->toBe(1);
});

test('missing Gmail credentials fail before contacting the server', function () {
    config(['mail.mailers.smtp.host' => 'smtp.gmail.com', 'mail.mailers.smtp.username' => 'sender@gmail.com',
        'mail.mailers.smtp.password' => '', 'mail.from.address' => 'sender@gmail.com']);
    Artisan::call('app:local-runtime-check', ['--json' => true]);
    $checks = json_decode(Artisan::output(), true)['checks'];
    expect($checks['mail_transport'])->toBeTrue()->and($checks['smtp_credentials'])->toBeFalse();
    $user = User::factory()->unverified()->create();
    $called = false;
    expect(fn () => app(EmailDeliveryService::class)->send($user, new QueuedVerifyEmail($user->email, $user), function () use (&$called) {
        $called = true;

        return null;
    }))
        ->toThrow(EmailDeliveryException::class);
    expect($called)->toBeFalse()->and(EmailDelivery::query()->sole()->failure_category)->toBe('provider_configuration');
});

test('interrupted SMTP jobs never automatically resubmit an ambiguously accepted message', function () {
    $user = User::factory()->unverified()->create();
    $notification = new QueuedVerifyEmail($user->email, $user);
    $service = app(EmailDeliveryService::class);
    $record = $service->record($user, $notification);
    $record->update(['status' => 'processing', 'attempts' => 1]);
    expect(fn () => $service->send($user, $notification, fn () => throw new LogicException('Must not send')))
        ->toThrow(EmailDeliveryException::class, 'earlier SMTP attempt was interrupted');
    expect($record->refresh()->status)->toBe('failed')
        ->and($record->failure_category)->toBe('smtp_acceptance_unconfirmed')->and($record->attempts)->toBe(1);
});

test('SMTP temporary rejection retries are bounded at five attempts', function () {
    $user = User::factory()->unverified()->create();
    $notification = new QueuedVerifyEmail($user->email, $user);
    $service = app(EmailDeliveryService::class);
    $calls = 0;
    $send = function () use (&$calls) {
        $calls++;
        throw new TransportException('Temporary rejection', 451);
    };
    for ($attempt = 0; $attempt < 5; $attempt++) {
        expect(fn () => $service->send($user, $notification, $send))->toThrow(EmailDeliveryException::class);
    }
    $service->send($user, $notification, $send);
    expect($calls)->toBe(5)->and(EmailDelivery::query()->sole()->status)->toBe('failed');
});

test('SMTP notifications use the shared tracked channel without network access', function () {
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->once()->andReturnUsing(fn ($message, $envelope) => new Symfony\Component\Mailer\SentMessage($message, $envelope ?? Envelope::create($message)));
    Mail::extend('smtp', fn () => $transport);
    Mail::purge('smtp');
    $user = User::factory()->unverified()->create();
    Notification::sendNow($user, new QueuedVerifyEmail($user->email, $user));
    expect(EmailDelivery::query()->sole()->status)->toBe('accepted');
});
