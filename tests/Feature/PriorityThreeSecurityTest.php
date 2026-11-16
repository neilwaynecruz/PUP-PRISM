<?php

use App\Enums\AssetConditionStatus;
use App\Enums\AssetLifecycleEventType;
use App\Enums\AssetStatus;
use App\Enums\BookingStatus;
use App\Enums\InventoryCountStatus;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Department;
use App\Models\HandoverLog;
use App\Models\InventoryCountSession;
use App\Models\Location;
use App\Models\Position;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\AssetLifecycleService;
use App\Services\Inventory\AssetTransferService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    (new RoleSeeder)->run();
});

function createSecurityUser(string $role, bool $twoFactor = true): User
{
    $position = Position::factory()->create();
    $factory = User::factory()->assignedPosition($position);

    if ($twoFactor && in_array($role, ['Admin', 'Supply Head'], true)) {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->create();
    $user->assignRole(Role::findByName($role, 'web'));

    return $user;
}

test('unverified privileged users do not hit a redirect loop between email verification and two-factor setup', function () {
    $unverifiedAdmin = User::factory()->unverified()->create();
    $unverifiedAdmin->assignRole('Admin');

    $this->actingAs($unverifiedAdmin)
        ->get(route('dashboard', absolute: false))
        ->assertRedirect(route('verification.notice', absolute: false));

    $this->actingAs($unverifiedAdmin)
        ->get(route('verification.notice', absolute: false))
        ->assertOk();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $unverifiedAdmin->id, 'hash' => sha1($unverifiedAdmin->email)],
    );

    $this->actingAs($unverifiedAdmin)
        ->get($verificationUrl)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    $this->actingAs($unverifiedAdmin->fresh())
        ->get(route('dashboard', absolute: false))
        ->assertRedirect(route('security.edit', absolute: false));

    $this->actingAs($unverifiedAdmin->fresh())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit', absolute: false))
        ->assertOk();
});

test('all five auxiliary e2e helper commands refuse production and non-dedicated databases', function () {
    /** @var Application $application */
    $application = app();
    $originalEnvironment = $application->environment();

    $commands = [
        ['e2e:create-handover', ['asset_tag_code' => 'TAG-1', 'from_user_id' => 1, 'to_user_id' => 2, 'token' => 'tok']],
        ['e2e:latest-requisition-id', ['requester_email' => 'user@example.test']],
        ['e2e:prepare-handover-verification', ['asset_tag_code' => 'TAG-1', 'recipient_email' => 'user@example.test', 'token' => 'tok']],
        ['e2e:user-id', ['email' => 'user@example.test']],
        ['e2e:verify-email', ['email' => 'user@example.test']],
    ];

    foreach ($commands as [$signature, $params]) {
        expect(Artisan::call($signature, $params))->not->toBe(0);

        $application['env'] = 'production';
        try {
            expect(Artisan::call($signature, $params))->not->toBe(0)
                ->and(Artisan::output())->toContain('cannot run in production');
        } finally {
            $application['env'] = $originalEnvironment;
        }
    }
});

test('api throttles invalid or missing bearer token requests before authentication rejection', function () {
    $this->getJson('/api/products')->assertUnauthorized();

    for ($i = 0; $i < 59; $i++) {
        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/products')
            ->assertUnauthorized();
    }

    $this->withHeader('Authorization', 'Bearer invalid-token')
        ->getJson('/api/products')
        ->assertStatus(429);

    $this->getJson('/api/products')
        ->assertStatus(429);
});

test('api requisition creation validates active non-trashed consumables and enforces idempotency keys', function () {
    Notification::fake();

    $admin = createSecurityUser('Admin');
    $token = $admin->createToken('erp-sync', ['read', 'write'])->plainTextToken;

    $consumable = Product::factory()->consumable()->create(['is_active' => true]);
    $inactive = Product::factory()->consumable()->create(['is_active' => false]);
    $trashed = Product::factory()->consumable()->create(['is_active' => true]);
    $trashed->delete();
    $assetProduct = Product::factory()->asset()->create(['is_active' => true]);

    foreach ([$inactive->id, $trashed->id, $assetProduct->id] as $invalidId) {
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/requisitions', [
                'lines' => [['product_id' => $invalidId, 'qty_requested' => 2]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.product_id']);
    }

    $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Idempotency-Key' => 'bad',
    ])->postJson('/api/requisitions', [
        'lines' => [['product_id' => $consumable->id, 'qty_requested' => 2]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['idempotency_key']);

    $payload = [
        'notes' => 'Quarterly paper request',
        'lines' => [['product_id' => $consumable->id, 'qty_requested' => 5]],
    ];

    $created = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Idempotency-Key' => 'req-key-2026-0001',
    ])->postJson('/api/requisitions', $payload)
        ->assertCreated();

    $createdId = $created->json('data.id');

    $replay = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Idempotency-Key' => 'req-key-2026-0001',
    ])->postJson('/api/requisitions', $payload)
        ->assertOk()
        ->assertHeader('X-Idempotent-Replay', 'true');

    expect($replay->json('data.id'))->toBe($createdId)
        ->and(Requisition::query()->count())->toBe(1);

    $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Idempotency-Key' => 'req-key-2026-0001',
    ])->postJson('/api/requisitions', [
        'notes' => 'Different qty same key',
        'lines' => [['product_id' => $consumable->id, 'qty_requested' => 9]],
    ])->assertStatus(409);
});

test('api scopes requisition listings by role, includes position department on assets, and filters condemn/return movements', function () {
    $custodianA = createSecurityUser('Property Custodian');
    $custodianB = createSecurityUser('Property Custodian');
    $supplyHead = createSecurityUser('Supply Head');

    $ownRequisition = Requisition::factory()->create(['requester_id' => $custodianA->id]);
    Requisition::factory()->create(['requester_id' => $custodianB->id]);

    $tokenCustodianA = $custodianA->createToken('custodian', ['read'])->plainTextToken;
    $tokenSupplyHead = $supplyHead->createToken('supply-head', ['read'])->plainTextToken;

    $custodianList = $this->withHeader('Authorization', "Bearer {$tokenCustodianA}")
        ->getJson('/api/requisitions')
        ->assertOk();

    expect(collect($custodianList->json('data'))->pluck('id')->all())->toBe([$ownRequisition->id]);

    $this->app['auth']->forgetGuards();

    $supplyHeadList = $this->withHeader('Authorization', "Bearer {$tokenSupplyHead}")
        ->getJson('/api/requisitions')
        ->assertOk();

    expect($supplyHeadList->json('meta.total'))->toBe(2);

    $department = Department::factory()->create(['name' => 'College of Engineering']);
    $position = Position::factory()->create(['department_id' => $department->id, 'title' => 'Lab Coordinator']);
    $asset = Asset::factory()->assignedToPosition($position)->create();

    $this->withHeader('Authorization', "Bearer {$tokenSupplyHead}")
        ->getJson('/api/assets')
        ->assertOk()
        ->assertJsonPath('data.0.position.department', 'College of Engineering');

    $this->withHeader('Authorization', "Bearer {$tokenSupplyHead}")
        ->getJson("/api/assets/{$asset->id}")
        ->assertOk()
        ->assertJsonPath('data.position.department', 'College of Engineering');

    StockMovement::factory()->create(['movement_type' => 'condemn', 'qty_delta' => 0]);
    StockMovement::factory()->create(['movement_type' => 'return', 'qty_delta' => 1]);

    $this->withHeader('Authorization', "Bearer {$tokenSupplyHead}")
        ->getJson('/api/stock-movements?movement_type=condemn')
        ->assertForbidden();

    $admin = createSecurityUser('Admin');
    $tokenAdmin = $admin->createToken('admin', ['read'])->plainTextToken;
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$tokenAdmin}")
        ->getJson('/api/stock-movements?movement_type=condemn')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->withHeader('Authorization', "Bearer {$tokenAdmin}")
        ->getJson('/api/stock-movements?movement_type=return')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

test('verified handover recipients are redirected according to their role and booking listings prevent cross-user leaks', function () {
    $custodian = createSecurityUser('Property Custodian');
    $supplyHead = createSecurityUser('Supply Head');
    $otherUser = createSecurityUser('Property Custodian');

    $plainToken = 'custodian-verify-token-2026';
    $handover = HandoverLog::factory()->create([
        'to_user_id' => $custodian->id,
        'to_position_id' => $custodian->position_id,
        'verification_token_hash' => hash('sha256', $plainToken),
    ]);

    $pngSignature = 'data:image/png;base64,'.base64_encode(
        "\x89PNG\r\n\x1a\n"
        ."\x00\x00\x00\x0dIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89"
        ."\x00\x00\x00\x0aIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4"
        ."\x00\x00\x00\x00IEND\xaeB`\x82"
    );

    $csrf = 'priority-three-handover-token';
    $this->actingAs($custodian)
        ->withSession(['_token' => $csrf, "handover_verify_token.{$handover->id}" => $plainToken])
        ->post(route('inventory.handover.verify.submit', $handover, absolute: false), [
            '_token' => $csrf,
            'signature_png' => $pngSignature,
        ])
        ->assertRedirect(route('inventory.handover.index', absolute: false));

    $ownBooking = Booking::factory()->create([
        'requester_id' => $supplyHead->id,
        'status' => BookingStatus::Requested,
    ]);
    Booking::factory()->create([
        'requester_id' => $otherUser->id,
        'status' => BookingStatus::Requested,
    ]);

    $this->actingAs($supplyHead)
        ->get(route('inventory.bookings.index', ['requester_id' => $otherUser->id], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('bookings.data', 1)
            ->where('bookings.data.0.id', $ownBooking->id)
            ->has('calendar_events', 1)
            ->has('approval_queue', 0));
});

test('bulk routes enforce upfront authorization on empty requests and validate existing ids, and users cannot deactivate themselves via policy', function () {
    $custodian = createSecurityUser('Property Custodian');
    $admin = createSecurityUser('Admin');
    $category = Category::factory()->create();
    $csrf = 'priority-three-bulk-token';

    $this->actingAs($custodian)
        ->withSession(['_token' => $csrf])
        ->post(route('inventory.products.bulk-activate', absolute: false), [
            '_token' => $csrf,
            'ids' => [],
        ])
        ->assertForbidden();

    $this->actingAs($custodian)
        ->withSession(['_token' => $csrf])
        ->post(route('inventory.products.bulk-change-category', absolute: false), [
            '_token' => $csrf,
            'ids' => [],
            'category_id' => $category->id,
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->withSession(['_token' => $csrf])
        ->post(route('inventory.products.bulk-activate', absolute: false), [
            '_token' => $csrf,
            'ids' => [999999],
        ])
        ->assertSessionHasErrors('ids.0');

    $this->actingAs($admin)
        ->withSession(['_token' => $csrf])
        ->post(route('inventory.bookings.bulk-approve', absolute: false), [
            '_token' => $csrf,
            'ids' => [999999],
        ])
        ->assertSessionHasErrors('ids.0');

    $this->actingAs($admin)
        ->withSession(['_token' => $csrf])
        ->post(route('inventory.requisitions.bulk-approve', absolute: false), [
            '_token' => $csrf,
            'ids' => [999999],
        ])
        ->assertSessionHasErrors('ids.0');

    expect($admin->can('deactivate', $admin))->toBeFalse();
});

test('attachment deletion is blocked after approved transfer, approved lifecycle event, or approved count session', function () {
    Storage::fake('local');

    $requester = createSecurityUser('Property Custodian');
    $approver = createSecurityUser('Admin');

    $fromLocation = Location::factory()->create(['is_active' => true]);
    $toLocation = Location::factory()->create(['is_active' => true]);
    $asset = Asset::factory()->create([
        'status' => AssetStatus::Available,
        'condition_status' => AssetConditionStatus::Serviceable,
        'current_location_id' => $fromLocation->id,
    ]);

    $transferService = app(AssetTransferService::class);
    $transfer = $transferService->create($requester, [
        'asset_id' => $asset->id,
        'to_location_id' => $toLocation->id,
        'transfer_date' => now()->toDateString(),
        'reason' => 'Move unit between buildings',
    ]);
    $transferAttachment = Attachment::factory()->create([
        'attachable_type' => $transfer::class,
        'attachable_id' => $transfer->id,
        'uploaded_by' => $requester->id,
    ]);
    expect($approver->can('delete', $transferAttachment))->toBeTrue();
    $transferService->approve($approver, $transfer);
    expect($approver->can('delete', $transferAttachment->fresh()))->toBeFalse();

    $lifecycleService = app(AssetLifecycleService::class);
    $event = $lifecycleService->propose($requester, [
        'asset_id' => $asset->id,
        'event_type' => AssetLifecycleEventType::ConditionChange->value,
        'condition_status' => AssetConditionStatus::Damaged->value,
        'reason' => 'Drop damage report',
    ]);
    $lifecycleAttachment = Attachment::factory()->create([
        'attachable_type' => $event::class,
        'attachable_id' => $event->id,
        'uploaded_by' => $requester->id,
    ]);
    $lifecycleService->approve($approver, $event);
    expect($approver->can('delete', $lifecycleAttachment->fresh()))->toBeFalse();

    $countSession = InventoryCountSession::factory()->create([
        'status' => InventoryCountStatus::Approved,
    ]);
    $countAttachment = Attachment::factory()->create([
        'attachable_type' => $countSession::class,
        'attachable_id' => $countSession->id,
        'uploaded_by' => $approver->id,
    ]);
    expect($approver->can('delete', $countAttachment->fresh()))->toBeFalse();
});

test('infrastructure hardens queue after_commit, web and api CSP headers, and prune command exits cleanly on invalid --only', function () {
    foreach (['database', 'beanstalkd', 'sqs', 'redis'] as $connection) {
        expect(config("queue.connections.{$connection}.after_commit"))->toBeTrue();
    }

    $webResponse = $this->get('/');
    expect($webResponse->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("'wasm-unsafe-eval'");

    $apiResponse = $this->getJson('/api/products');
    expect($apiResponse->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($apiResponse->headers->get('Content-Security-Policy'))->toContain("default-src 'self'");

    $exitCode = Artisan::call('app:prune-operational-data', ['--only' => 'nonexistent_table']);
    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Unknown --only value');
});
