<?php

use App\Enums\RequisitionStatus;
use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\AssetTransfer;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\HandoverLog;
use App\Models\Position;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\User;
use App\Policies\HandoverLogPolicy;
use App\Services\Inventory\BulkTrashService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    (new RoleSeeder)->run();
});

function createGovernanceUser(string $role): User
{
    $position = Position::factory()->create();
    $factory = User::factory()->assignedPosition($position);

    if (in_array($role, ['Admin', 'Supply Head'], true)) {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->create();
    $user->assignRole(Role::findByName($role, 'web'));

    return $user;
}

test('workflow models block mass assignment of sensitive status and approval attributes', function () {
    $requisition = new Requisition([
        'notes' => 'Office supplies request',
        'status' => RequisitionStatus::Approved->value,
        'approver_id' => 999,
        'approved_ip_address' => '127.0.0.1',
    ]);
    expect($requisition->notes)->toBe('Office supplies request')
        ->and($requisition->getAttributes())->not->toHaveKeys(['status', 'approver_id', 'approved_ip_address']);

    $booking = new Booking([
        'purpose' => 'Department seminar',
        'status' => 'approved',
        'approver_id' => 999,
        'approved_ip_address' => '127.0.0.1',
    ]);
    expect($booking->purpose)->toBe('Department seminar')
        ->and($booking->getAttributes())->not->toHaveKeys(['status', 'approver_id', 'approved_ip_address']);

    $purchaseOrder = new PurchaseOrder([
        'notes' => 'Deliver to supply room',
        'po_number' => 'PO-FAKE-0001',
        'status' => 'received',
        'approved_by' => 999,
    ]);
    expect($purchaseOrder->notes)->toBe('Deliver to supply room')
        ->and($purchaseOrder->getAttributes())->not->toHaveKeys(['po_number', 'status', 'approved_by']);

    $handoverLog = new HandoverLog([
        'otp_hash' => 'forged-hash',
        'status' => 'verified',
        'otp_expires_at' => now()->addHour(),
        'verified_ip_address' => '127.0.0.1',
    ]);
    expect($handoverLog->getAttributes())->not->toHaveKeys(['otp_hash', 'status', 'otp_expires_at', 'verified_ip_address']);

    $assetTransfer = new AssetTransfer([
        'reason' => 'Lab relocation',
        'status' => 'approved',
        'approved_by' => 999,
    ]);
    expect($assetTransfer->reason)->toBe('Lab relocation')
        ->and($assetTransfer->getAttributes())->not->toHaveKeys(['status', 'approved_by']);

    $lifecycleEvent = new AssetLifecycleEvent([
        'reason' => 'End of useful life',
        'status' => 'approved',
        'approved_by' => 999,
    ]);
    expect($lifecycleEvent->reason)->toBe('End of useful life')
        ->and($lifecycleEvent->getAttributes())->not->toHaveKeys(['status', 'approved_by']);
});

test('all expected policies including HandoverLogPolicy are registered cleanly', function () {
    expect(count(Gate::policies()))->toBeGreaterThanOrEqual(23)
        ->and(Gate::getPolicyFor(HandoverLog::class))->toBeInstanceOf(HandoverLogPolicy::class)
        ->and(Gate::getPolicyFor(Requisition::class))->not->toBeNull()
        ->and(Gate::getPolicyFor(Booking::class))->not->toBeNull()
        ->and(Gate::getPolicyFor(PurchaseOrder::class))->not->toBeNull()
        ->and(Gate::getPolicyFor(AssetTransfer::class))->not->toBeNull()
        ->and(Gate::getPolicyFor(AssetLifecycleEvent::class))->not->toBeNull();
});

test('handover log policy permits custodians and admins while blocking supply head and verifying recipient', function () {
    $admin = createGovernanceUser('Admin');
    $supplyHead = createGovernanceUser('Supply Head');
    $sender = createGovernanceUser('Property Custodian');
    $recipient = createGovernanceUser('Property Custodian');
    $asset = Asset::factory()->create();

    $log = HandoverLog::factory()->create([
        'asset_id' => $asset->id,
        'from_user_id' => $sender->id,
        'to_user_id' => $recipient->id,
    ]);

    expect(Gate::forUser($admin)->allows('viewAny', HandoverLog::class))->toBeTrue()
        ->and(Gate::forUser($sender)->allows('create', HandoverLog::class))->toBeTrue()
        ->and(Gate::forUser($supplyHead)->allows('viewAny', HandoverLog::class))->toBeFalse()
        ->and(Gate::forUser($supplyHead)->allows('cancel', $log))->toBeFalse()
        ->and(Gate::forUser($recipient)->allows('verify', $log))->toBeTrue()
        ->and(Gate::forUser($sender)->allows('verify', $log))->toBeFalse();
});

test('api requisition show enforces ownership and central trash index enforces admin authorization', function () {
    $admin = createGovernanceUser('Admin');
    $custodianA = createGovernanceUser('Property Custodian');
    $custodianB = createGovernanceUser('Property Custodian');

    $requisition = Requisition::factory()->create([
        'requester_id' => $custodianA->id,
        'requester_position_id' => $custodianA->position_id,
    ]);

    Sanctum::actingAs($custodianB, ['read']);
    $this->getJson(route('api.requisitions.show', $requisition, absolute: false))
        ->assertForbidden();

    Sanctum::actingAs($custodianA, ['read']);
    $this->getJson(route('api.requisitions.show', $requisition, absolute: false))
        ->assertOk();

    $this->actingAs($custodianA)
        ->get(route('inventory.trash', absolute: false))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('inventory.trash', absolute: false))
        ->assertOk();
});

test('bulk trash service restores and permanently deletes trashed products with audit logs', function () {
    $admin = createGovernanceUser('Admin');
    $restorable = Product::factory()->create(['name' => 'Restore Target', 'sku' => 'RES-001']);
    $deletable = Product::factory()->create(['name' => 'Delete Target', 'sku' => 'DEL-001']);
    $protected = Product::factory()->create(['name' => 'Protected Target', 'sku' => 'PRO-001']);

    $restorable->delete();
    $deletable->delete();
    $protected->delete();

    $service = app(BulkTrashService::class);

    $this->actingAs($admin);

    $restoredCount = $service->bulkRestore(
        modelClass: Product::class,
        ids: [$restorable->id],
        actor: $admin,
        labelResolver: fn (Product $p): string => $p->sku,
    );

    expect($restoredCount)->toBe(1)
        ->and($restorable->fresh()->deleted_at)->toBeNull()
        ->and(AuditLog::query()->where('action', 'restore')->where('model_id', $restorable->id)->exists())->toBeTrue();

    $result = $service->bulkForceDelete(
        modelClass: Product::class,
        ids: [$deletable->id, $protected->id],
        actor: $admin,
        canForceDelete: fn (Product $p): bool => $p->id !== $protected->id,
    );

    expect($result)->toBe(['deleted' => 1, 'skipped' => 1])
        ->and(Product::withTrashed()->find($deletable->id))->toBeNull()
        ->and(Product::withTrashed()->find($protected->id))->not->toBeNull();
});

test('public manifest and duplicate numbered icon assets are removed cleanly', function () {
    expect(file_exists(public_path('manifest.json')))->toBeFalse()
        ->and(glob(public_path('Icons/* (2).*')) ?: [])->toBeEmpty()
        ->and(file_exists(public_path('Icons/prism-icon-192x192.png')))->toBeTrue()
        ->and(file_exists(public_path('Icons/prism-icon-512x512.png')))->toBeTrue();
});
