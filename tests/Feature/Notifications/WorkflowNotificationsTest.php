<?php

use App\Enums\AssetStatus;
use App\Enums\BookingStatus;
use App\Enums\ProductType;
use App\Enums\RequisitionStatus;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Position;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\User;
use App\Notifications\BookingStatusChangedNotification;
use App\Notifications\BookingSubmittedNotification;
use App\Notifications\HandoverVerificationNotification;
use App\Notifications\LowStockAlertNotification;
use App\Notifications\ProcurementRecommendationNotification;
use App\Notifications\RequisitionStatusChangedNotification;
use App\Notifications\RequisitionSubmittedNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses()->group('notifications');

/**
 * @return array{supplyHead: User, requester: User}
 */
function workflowRequisitionActors(): array
{
    Notification::fake();

    (new RoleSeeder)->run();

    $supplyHead = User::factory()->create();
    $supplyHead->assignRole('Supply Head');

    $requester = User::factory()->create([
        'position_id' => Position::factory()->create()->id,
    ]);

    return compact('supplyHead', 'requester');
}

/**
 * @return array{custodian: User, requester: User, product: Product, asset: Asset}
 */
function workflowBookingActors(): array
{
    Notification::fake();

    (new RoleSeeder)->run();

    $custodian = User::factory()->create();
    $custodian->assignRole('Property Custodian');

    $requester = User::factory()->create([
        'position_id' => Position::factory()->create()->id,
    ]);

    $product = Product::factory()->create([
        'type' => ProductType::Asset,
        'is_active' => true,
    ]);

    $asset = Asset::factory()->create([
        'product_id' => $product->id,
        'status' => AssetStatus::Available,
    ]);

    return compact('custodian', 'requester', 'product', 'asset');
}

/**
 * @return array{supplyHead: User}
 */
function workflowSupplyHeadActor(): array
{
    Notification::fake();

    (new RoleSeeder)->run();

    $supplyHead = User::factory()->create();
    $supplyHead->assignRole('Supply Head');

    return compact('supplyHead');
}

describe('Requisition workflow notifications', function () {
    it('notifies supply head when a requisition is submitted', function () {
        ['requester' => $requester] = workflowRequisitionActors();

        $requisition = Requisition::factory()->create([
            'requester_id' => $requester->id,
            'status' => RequisitionStatus::Submitted,
        ]);

        $requester->notify(new RequisitionSubmittedNotification($requisition));

        Notification::assertSentTo($requester, RequisitionSubmittedNotification::class);
    });

    it('notifies requester when requisition is approved', function () {
        ['requester' => $requester] = workflowRequisitionActors();

        $requisition = Requisition::factory()->create([
            'requester_id' => $requester->id,
            'status' => RequisitionStatus::Approved,
        ]);

        $requester->notify(new RequisitionStatusChangedNotification($requisition, 'approved'));

        Notification::assertSentTo($requester, RequisitionStatusChangedNotification::class, function ($notification) {
            return $notification->action === 'approved';
        });
    });

    it('notifies requester when requisition is rejected', function () {
        ['requester' => $requester] = workflowRequisitionActors();

        $requisition = Requisition::factory()->create([
            'requester_id' => $requester->id,
            'status' => RequisitionStatus::Rejected,
        ]);

        $requester->notify(new RequisitionStatusChangedNotification($requisition, 'rejected'));

        Notification::assertSentTo($requester, RequisitionStatusChangedNotification::class, function ($notification) {
            return $notification->action === 'rejected';
        });
    });

    it('notifies requester when requisition is issued', function () {
        ['requester' => $requester] = workflowRequisitionActors();

        $requisition = Requisition::factory()->create([
            'requester_id' => $requester->id,
            'status' => RequisitionStatus::Issued,
        ]);

        $requester->notify(new RequisitionStatusChangedNotification($requisition, 'issued'));

        Notification::assertSentTo($requester, RequisitionStatusChangedNotification::class, function ($notification) {
            return $notification->action === 'issued';
        });
    });
});

describe('Booking workflow notifications', function () {
    it('notifies property custodians when a booking is requested', function () {
        ['custodian' => $custodian, 'requester' => $requester, 'asset' => $asset] = workflowBookingActors();

        $booking = Booking::factory()->create([
            'asset_id' => $asset->id,
            'requester_id' => $requester->id,
            'status' => BookingStatus::Requested,
        ]);

        $custodian->notify(new BookingSubmittedNotification($booking));

        Notification::assertSentTo($custodian, BookingSubmittedNotification::class);
    });

    it('notifies requester when booking is approved', function () {
        ['requester' => $requester, 'asset' => $asset] = workflowBookingActors();

        $booking = Booking::factory()->create([
            'asset_id' => $asset->id,
            'requester_id' => $requester->id,
            'status' => BookingStatus::Approved,
        ]);

        $requester->notify(new BookingStatusChangedNotification($booking, 'approved'));

        Notification::assertSentTo($requester, BookingStatusChangedNotification::class, function ($notification) {
            return $notification->action === 'approved';
        });
    });

    it('notifies requester when booking is rejected', function () {
        ['requester' => $requester, 'asset' => $asset] = workflowBookingActors();

        $booking = Booking::factory()->create([
            'asset_id' => $asset->id,
            'requester_id' => $requester->id,
            'status' => BookingStatus::Rejected,
        ]);

        $requester->notify(new BookingStatusChangedNotification($booking, 'rejected'));

        Notification::assertSentTo($requester, BookingStatusChangedNotification::class, function ($notification) {
            return $notification->action === 'rejected';
        });
    });
});

describe('Low stock alert notification', function () {
    it('notifies supply head when product stock is low', function () {
        ['supplyHead' => $supplyHead] = workflowSupplyHeadActor();

        $product = Product::factory()->create([
            'reorder_threshold' => 10,
        ]);

        $supplyHead->notify(new LowStockAlertNotification($product, 5));

        Notification::assertSentTo($supplyHead, LowStockAlertNotification::class, function ($notification) {
            return $notification->currentStock === 5;
        });
    });
});

describe('Queued notification delivery', function () {
    it('queues workflow notifications on the notifications queue', function () {
        Queue::fake();
        config(['queue.default' => 'database']);

        $requester = User::factory()->create([
            'position_id' => Position::factory()->create()->id,
        ]);

        $requisition = Requisition::factory()->create([
            'requester_id' => $requester->id,
            'status' => RequisitionStatus::Submitted,
        ]);

        $requester->notify(new RequisitionSubmittedNotification($requisition));

        Queue::assertPushedOn('notifications', SendQueuedNotifications::class);
    });

    it('still delivers database notifications when the queue connection is sync', function () {
        config(['queue.default' => 'sync']);

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'reorder_threshold' => 10,
        ]);

        $user->notify(new LowStockAlertNotification($product, 3));

        expect($user->notifications()->count())->toBe(1);
    });

    it('implements ShouldQueue with retry and queue configuration', function (Closure $factory) {
        $notification = $factory();

        expect($notification)->toBeInstanceOf(ShouldQueue::class)
            ->and($notification->tries)->toBe(3)
            ->and($notification->queue)->toBe('notifications');
    })->with([
        'requisition submitted' => [fn () => new RequisitionSubmittedNotification(Requisition::factory()->make())],
        'requisition status changed' => [fn () => new RequisitionStatusChangedNotification(Requisition::factory()->make(), 'approved')],
        'booking submitted' => [fn () => new BookingSubmittedNotification(Booking::factory()->make())],
        'booking status changed' => [fn () => new BookingStatusChangedNotification(Booking::factory()->make(), 'approved')],
        'low stock alert' => [fn () => new LowStockAlertNotification(Product::factory()->make(), 4)],
        'procurement recommendation' => [fn () => new ProcurementRecommendationNotification(Product::factory()->make(), 5, 12)],
        'handover verification' => [fn () => new HandoverVerificationNotification(10, 'verification-token')],
    ]);
});

describe('Notification delivery channels', function () {
    it('adds database and broadcast delivery to requisition notifications', function () {
        $requester = User::factory()->create([
            'position_id' => Position::factory()->create()->id,
        ]);

        $requisition = Requisition::factory()->create([
            'requester_id' => $requester->id,
        ]);

        $submitted = new RequisitionSubmittedNotification($requisition);
        $statusChanged = new RequisitionStatusChangedNotification($requisition, 'approved');

        expect($submitted->via($requester))->toBe(['mail', 'database', 'broadcast']);
        expect($statusChanged->via($requester))->toBe(['mail', 'database', 'broadcast']);
        expect($submitted->toArray($requester))->toMatchArray([
            'category' => 'requisition',
            'severity' => 'info',
        ]);
    });

    it('adds database and broadcast delivery to booking notifications', function () {
        $requester = User::factory()->create([
            'position_id' => Position::factory()->create()->id,
        ]);

        $product = Product::factory()->create([
            'type' => ProductType::Asset,
            'is_active' => true,
        ]);

        $asset = Asset::factory()->create([
            'product_id' => $product->id,
            'status' => AssetStatus::Available,
        ]);

        $booking = Booking::factory()->create([
            'asset_id' => $asset->id,
            'requester_id' => $requester->id,
        ]);

        $submitted = new BookingSubmittedNotification($booking);
        $statusChanged = new BookingStatusChangedNotification($booking, 'approved');

        expect($submitted->via($requester))->toBe(['mail', 'database', 'broadcast']);
        expect($statusChanged->via($requester))->toBe(['mail', 'database', 'broadcast']);
        expect($statusChanged->toArray($requester))->toMatchArray([
            'category' => 'booking',
            'severity' => 'success',
        ]);
    });

    it('adds database and broadcast delivery to alert and handover notifications', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'reorder_threshold' => 10,
        ]);

        $lowStock = new LowStockAlertNotification($product, 4);
        $handover = new HandoverVerificationNotification(10, 'verification-token');

        expect($lowStock->via($user))->toBe(['mail', 'database', 'broadcast']);
        expect($handover->via($user))->toBe(['mail', 'database', 'broadcast']);
        expect($handover->toArray($user))->toMatchArray([
            'category' => 'handover',
            'severity' => 'info',
        ]);
    });
});
