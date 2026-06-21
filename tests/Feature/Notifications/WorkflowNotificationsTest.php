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
use App\Notifications\RequisitionStatusChangedNotification;
use App\Notifications\RequisitionSubmittedNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;

uses()->group('notifications');

describe('Requisition workflow notifications', function () {
    beforeEach(function () {
        Notification::fake();

        (new RoleSeeder)->run();

        $this->supplyHead = User::factory()->create();
        $this->supplyHead->assignRole('Supply Head');

        $this->requester = User::factory()->create([
            'position_id' => Position::factory()->create()->id,
        ]);
    });

    it('notifies supply head when a requisition is submitted', function () {
        $requisition = Requisition::factory()->create([
            'requester_id' => $this->requester->id,
            'status' => RequisitionStatus::Submitted,
        ]);

        $this->requester->notify(new RequisitionSubmittedNotification($requisition));

        Notification::assertSentTo($this->requester, RequisitionSubmittedNotification::class);
    });

    it('notifies requester when requisition is approved', function () {
        $requisition = Requisition::factory()->create([
            'requester_id' => $this->requester->id,
            'status' => RequisitionStatus::Approved,
        ]);

        $this->requester->notify(new RequisitionStatusChangedNotification($requisition, 'approved'));

        Notification::assertSentTo($this->requester, RequisitionStatusChangedNotification::class, function ($notification) {
            return $notification->action === 'approved';
        });
    });

    it('notifies requester when requisition is rejected', function () {
        $requisition = Requisition::factory()->create([
            'requester_id' => $this->requester->id,
            'status' => RequisitionStatus::Rejected,
        ]);

        $this->requester->notify(new RequisitionStatusChangedNotification($requisition, 'rejected'));

        Notification::assertSentTo($this->requester, RequisitionStatusChangedNotification::class, function ($notification) {
            return $notification->action === 'rejected';
        });
    });

    it('notifies requester when requisition is issued', function () {
        $requisition = Requisition::factory()->create([
            'requester_id' => $this->requester->id,
            'status' => RequisitionStatus::Issued,
        ]);

        $this->requester->notify(new RequisitionStatusChangedNotification($requisition, 'issued'));

        Notification::assertSentTo($this->requester, RequisitionStatusChangedNotification::class, function ($notification) {
            return $notification->action === 'issued';
        });
    });
});

describe('Booking workflow notifications', function () {
    beforeEach(function () {
        Notification::fake();

        (new RoleSeeder)->run();

        $this->custodian = User::factory()->create();
        $this->custodian->assignRole('Property Custodian');

        $this->requester = User::factory()->create([
            'position_id' => Position::factory()->create()->id,
        ]);

        $this->product = Product::factory()->create([
            'type' => ProductType::Asset,
            'is_active' => true,
        ]);

        $this->asset = Asset::factory()->create([
            'product_id' => $this->product->id,
            'status' => AssetStatus::Available,
        ]);
    });

    it('notifies property custodians when a booking is requested', function () {
        $booking = Booking::factory()->create([
            'asset_id' => $this->asset->id,
            'requester_id' => $this->requester->id,
            'status' => BookingStatus::Requested,
        ]);

        $this->custodian->notify(new BookingSubmittedNotification($booking));

        Notification::assertSentTo($this->custodian, BookingSubmittedNotification::class);
    });

    it('notifies requester when booking is approved', function () {
        $booking = Booking::factory()->create([
            'asset_id' => $this->asset->id,
            'requester_id' => $this->requester->id,
            'status' => BookingStatus::Approved,
        ]);

        $this->requester->notify(new BookingStatusChangedNotification($booking, 'approved'));

        Notification::assertSentTo($this->requester, BookingStatusChangedNotification::class, function ($notification) {
            return $notification->action === 'approved';
        });
    });

    it('notifies requester when booking is rejected', function () {
        $booking = Booking::factory()->create([
            'asset_id' => $this->asset->id,
            'requester_id' => $this->requester->id,
            'status' => BookingStatus::Rejected,
        ]);

        $this->requester->notify(new BookingStatusChangedNotification($booking, 'rejected'));

        Notification::assertSentTo($this->requester, BookingStatusChangedNotification::class, function ($notification) {
            return $notification->action === 'rejected';
        });
    });
});

describe('Low stock alert notification', function () {
    beforeEach(function () {
        Notification::fake();

        (new RoleSeeder)->run();

        $this->supplyHead = User::factory()->create();
        $this->supplyHead->assignRole('Supply Head');
    });

    it('notifies supply head when product stock is low', function () {
        $product = Product::factory()->create([
            'reorder_threshold' => 10,
        ]);

        $this->supplyHead->notify(new LowStockAlertNotification($product, 5));

        Notification::assertSentTo($this->supplyHead, LowStockAlertNotification::class, function ($notification) {
            return $notification->currentStock === 5;
        });
    });
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
