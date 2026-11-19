<?php

use App\Enums\NotificationEventType;
use App\Models\EmailDelivery;
use App\Models\HandoverLog;
use App\Models\User;
use App\Notifications\HandoverVerificationNotification;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use App\Services\Operations\EmailDeliveryException;
use App\Services\Operations\EmailDeliveryService;
use App\Services\Operations\NotificationMailMiddleware;
use App\Services\Operations\OperationalDataRetentionService;
use App\Services\Operations\SystemHealthService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;

/** @param list<Response|ConnectException> $responses */
function emailHttpFake(array $responses, array &$history): void
{
    config(['mail.default' => 'resend', 'services.resend.key' => 're_test_placeholder']);
    $handler = HandlerStack::create(new MockHandler($responses));
    $handler->push(Middleware::history($history));
    app()->bind(Client::class, fn () => new Client(['handler' => $handler]));
    Mail::purge('resend');
}

test('Resend acceptance is recorded once and response loss replays identical encrypted payload bytes', function () {
    $history = [];
    emailHttpFake([
        new ConnectException('Simulated response loss', new Request('POST', 'https://api.resend.com/emails')),
        new Response(200, ['Content-Type' => 'application/json'], '{"id":"provider-test-id"}'),
    ], $history);
    $user = User::factory()->unverified()->create();
    $notification = new QueuedVerifyEmail($user->email, $user);
    $notification->id = (string) Str::uuid();

    expect(fn () => Notification::sendNow($user, $notification))->toThrow(EmailDeliveryException::class);
    $record = EmailDelivery::query()->sole();
    expect($record->status)->toBe('retry_scheduled')->and($record->attempts)->toBe(1)
        ->and($record->request_payload)->toContain('Verify');
    expect(DB::table('email_deliveries')->value('request_payload'))->not->toContain('Verify');

    $this->travel(61)->seconds();
    Notification::sendNow($user, $notification);
    Notification::sendNow($user, $notification);

    expect($history)->toHaveCount(2)
        ->and((string) $history[0]['request']->getBody())->toBe((string) $history[1]['request']->getBody())
        ->and($history[0]['request']->getHeaderLine('Idempotency-Key'))->toBe($history[1]['request']->getHeaderLine('Idempotency-Key'))
        ->and($record->refresh()->status)->toBe('accepted')
        ->and($record->provider_id)->toBe('provider-test-id')
        ->and($record->request_payload)->toBeNull();
});

test('permanent rejection stops while temporary errors schedule bounded retries', function (int $status, string $name, bool $permanent) {
    $history = [];
    emailHttpFake([new Response($status, ['Retry-After' => '120', 'Content-Type' => 'application/json'], json_encode(['name' => $name, 'message' => 'Rejected', 'statusCode' => $status]))], $history);
    $user = User::factory()->unverified()->create();
    expect(fn () => Notification::sendNow($user, new QueuedVerifyEmail($user->email, $user)))->toThrow(EmailDeliveryException::class);
    expect(EmailDelivery::query()->sole()->status)->toBe($permanent ? 'failed' : 'retry_scheduled')
        ->and(app(EmailDeliveryService::class)->permanentFailure)->toBe($permanent)
        ->and(app(EmailDeliveryService::class)->retryDelay)->toBeGreaterThanOrEqual(120);
})->with([[401, 'validation_error', true], [422, 'validation_error', true], [409, 'invalid_idempotent_request', true], [409, 'concurrent_idempotent_requests', false], [429, 'rate_limit_exceeded', false], [503, 'application_error', false]]);

test('verification link is absolute fixed signed and rejected after verification or expiry', function () {
    config(['mail.default' => 'resend']);
    URL::forceRootUrl('https://inventory.example.test');
    URL::forceScheme('https');
    $user = User::factory()->unverified()->create();
    $notification = new QueuedVerifyEmail($user->email, $user);
    $url = $notification->toMail($user)->actionUrl;
    expect($url)->toStartWith('https://inventory.example.test/')
        ->and(URL::hasValidSignature(Illuminate\Http\Request::create($url)))->toBeTrue();
    $this->travel(2)->minutes();
    expect($notification->toMail($user)->actionUrl)->toBe($url);
    $user->markEmailAsVerified();
    expect($notification->shouldSend($user, 'mail'))->toBeFalse()
        ->and(EmailDelivery::query()->sole()->status)->toBe('superseded');
    $this->travel(60)->minutes();
    expect($notification->shouldSend($user, 'mail'))->toBeFalse();
});

test('reset messages become obsolete when a fresh token replaces them', function () {
    config(['mail.default' => 'resend']);
    $user = User::factory()->create();
    $token = Password::createToken($user);
    $notification = new QueuedResetPassword($token, $user->email, $user);
    expect($notification->shouldSend($user, 'mail'))->toBeTrue()
        ->and($notification->toMail($user)->actionUrl)->toContain('/reset-password/'.$token);
    Password::createToken($user);
    expect($notification->shouldSend($user, 'mail'))->toBeFalse()
        ->and(EmailDelivery::query()->sole()->status)->toBe('superseded');
});

test('security notifications use encrypted jobs and security queue independently of preferences', function () {
    $realQueue = app('queue')->connection('database');
    Queue::fake();
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();
    $user->sendPasswordResetNotification('sensitive-reset-token');
    Queue::assertPushed(SendQueuedNotifications::class, 2);
    Queue::assertPushed(SendQueuedNotifications::class, fn ($job) => $job->queue === 'security' && $job->shouldBeEncrypted && $job->afterCommit);
    $job = new SendQueuedNotifications($user, new QueuedResetPassword('sensitive-reset-token', $user->email, $user), ['mail']);
    $realQueue->push($job->beforeCommit(), '', 'security');
    $payload = DB::table('jobs')->value('payload');
    expect($payload)->not->toContain('sensitive-reset-token')->not->toContain($user->email);
});

test('cancelled and rotated handovers do not send stale links and never auto accept', function () {
    config(['mail.default' => 'resend']);
    $user = User::factory()->create();
    $handover = HandoverLog::factory()->create(['to_user_id' => $user->id, 'verification_token_hash' => hash('sha256', 'current-token')]);
    $notification = new HandoverVerificationNotification($handover->id, 'current-token');
    expect($notification->via($user))->toContain('mail')
        ->and($notification->toMail($user)->actionUrl)->toStartWith('http')
        ->and($notification->shouldSend($user, 'mail'))->toBeTrue();
    $handover->forceFill(['verification_token_hash' => hash('sha256', 'new-token')])->save();
    expect($notification->shouldSend($user, 'mail'))->toBeFalse()->and($handover->refresh()->isPending())->toBeTrue();
    $replacement = new HandoverVerificationNotification($handover->id, 'new-token');
    $handover->forceFill(['cancelled_at' => now()])->save();
    expect($replacement->shouldSend($user, 'mail'))->toBeFalse();
});

test('expired messages are never sent and retention removes their encrypted contents', function () {
    $history = [];
    emailHttpFake([], $history);
    $user = User::factory()->unverified()->create();
    $notification = new QueuedVerifyEmail($user->email, $user);
    $record = app(EmailDeliveryService::class)->record($user, $notification);
    $record->update(['request_payload' => 'sensitive data']);
    $this->travel(2)->hours();
    Notification::sendNow($user, $notification);
    expect($history)->toBeEmpty()->and($record->refresh()->status)->toBe('expired')->and($record->request_payload)->toBeNull();
    app(OperationalDataRetentionService::class)->pruneEmailDeliveries();
    expect(EmailDelivery::query()->count())->toBe(1);
    $this->travel(31)->days();
    expect(app(OperationalDataRetentionService::class)->pruneEmailDeliveries())->toBe(1);
});

test('Operations Health does not equate accepted mail with inbox delivery and never exposes content', function () {
    config(['mail.default' => 'resend']);
    EmailDelivery::factory()->create(['status' => 'accepted', 'provider_id' => 'provider-id', 'request_payload' => 'secret message']);
    $check = collect(app(SystemHealthService::class)->report()['checks'])->firstWhere('key', 'email');
    expect(json_encode($check))->toContain('Accepted by transport (not proof of delivery)')->not->toContain('secret message');
});

test('mail retries stop after five transport attempts without sending again', function () {
    $history = [];
    emailHttpFake(array_fill(0, 5, new Response(503, ['Content-Type' => 'application/json'], '{"name":"application_error","message":"Unavailable","statusCode":503}')), $history);
    $user = User::factory()->unverified()->create();
    $notification = new QueuedVerifyEmail($user->email, $user);
    $notification->id = (string) Str::uuid();
    for ($attempt = 0; $attempt < 5; $attempt++) {
        expect(fn () => Notification::sendNow($user, $notification))->toThrow(EmailDeliveryException::class);
    }
    Notification::sendNow($user, $notification);
    expect($history)->toHaveCount(5)
        ->and(EmailDelivery::query()->sole()->status)->toBe('failed')
        ->and(EmailDelivery::query()->sole()->request_payload)->toBeNull();
});

test('rolled back transactions enqueue nothing while committed transactions create tracked encrypted jobs', function () {
    config(['queue.default' => 'database', 'mail.default' => 'resend']);
    $user = User::factory()->unverified()->create();
    DB::beginTransaction();
    $user->sendEmailVerificationNotification();
    expect(DB::table('jobs')->count())->toBe(0);
    DB::rollBack();
    expect(DB::table('jobs')->count())->toBe(0)->and(EmailDelivery::query()->count())->toBe(0);

    DB::transaction(function () use ($user): void {
        $user->sendEmailVerificationNotification();
        expect(DB::table('jobs')->count())->toBe(0);
    });
    expect(DB::table('jobs')->count())->toBe(1)
        ->and(DB::table('jobs')->value('queue'))->toBe('security')
        ->and(EmailDelivery::query()->sole()->status)->toBe('queued');
});

test('lock contention does not send and accepted status cannot be downgraded by a stale message', function () {
    config(['mail.default' => 'resend']);
    $user = User::factory()->unverified()->create();
    $notification = new QueuedVerifyEmail($user->email, $user);
    $service = app(EmailDeliveryService::class);
    $record = $service->record($user, $notification);
    $lock = Cache::lock('email-delivery:'.$record->id, 60);
    $lock->get();
    try {
        expect(fn () => $service->send($user, $notification, fn () => throw new LogicException('Must not send')))->toThrow(EmailDeliveryException::class);
    } finally {
        $lock->release();
    }
    $record->update(['status' => 'accepted', 'provider_id' => 'already-accepted']);
    $service->skip($user, $notification, 'superseded');
    expect($record->refresh()->status)->toBe('accepted');
});

test('queued middleware releases temporary failures and fails permanent or application errors', function () {
    $user = User::factory()->unverified()->create();
    $notification = new QueuedVerifyEmail($user->email, $user);
    $middleware = new NotificationMailMiddleware;
    $delivery = app(EmailDeliveryService::class);
    $delivery->retryDelay = 120;
    $job = (new SendQueuedNotifications($user, $notification, ['mail']))->withFakeQueueInteractions();
    $middleware->handle($job, fn () => throw new EmailDeliveryException('Temporary'));
    $job->assertReleased(120)->assertNotFailed();

    $delivery->permanentFailure = true;
    $job = (new SendQueuedNotifications($user, $notification, ['mail']))->withFakeQueueInteractions();
    $middleware->handle($job, fn () => throw new EmailDeliveryException('Permanent'));
    $job->assertFailed()->assertNotReleased();

    $job = (new SendQueuedNotifications($user, $notification, ['mail']))->withFakeQueueInteractions();
    $middleware->handle($job, fn () => throw new LogicException('Application error'));
    $job->assertFailedWith(LogicException::class)->assertNotReleased();
});

test('nested Resend concurrent errors remain retryable and missing provider evidence never claims acceptance', function () {
    $history = [];
    emailHttpFake([new Response(409, ['Content-Type' => 'application/json'], '{"error":{"name":"concurrent_idempotent_requests","message":"Busy","statusCode":409}}')], $history);
    $user = User::factory()->unverified()->create();
    expect(fn () => Notification::sendNow($user, new QueuedVerifyEmail($user->email, $user)))->toThrow(EmailDeliveryException::class);
    expect(EmailDelivery::query()->sole()->status)->toBe('retry_scheduled');

    $notification = new QueuedVerifyEmail($user->email, $user);
    $service = app(EmailDeliveryService::class);
    $email = (new Email)->from('test@example.test')->to($user->email)->text('Test');
    $message = new SentMessage(new Symfony\Component\Mailer\SentMessage($email, Envelope::create($email)));
    expect(fn () => $service->send($user, $notification, fn () => $message))->toThrow(EmailDeliveryException::class);
    expect($service->record($user, $notification)->status)->toBe('failed');
});

test('muting handover preferences cannot suppress the required security email', function () {
    $user = User::factory()->create();
    $user->notificationPreferences()->create([
        'event_type' => NotificationEventType::HandoverVerification->value,
        'mail_enabled' => false, 'database_enabled' => true, 'broadcast_enabled' => false,
        'digest_frequency' => 'daily',
    ]);
    expect((new HandoverVerificationNotification(1, 'test-token'))->via($user))->toBe(['mail', 'database']);
});
