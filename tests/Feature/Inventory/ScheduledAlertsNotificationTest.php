<?php

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\User;
use App\Notifications\ExpiringStockAlertNotification;
use App\Notifications\LowStockAlertNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');

});

test('scheduled inventory alert command dispatches notifications for newly detected low stock and expiring lots', function () {
    Notification::fake();

    $supplyHead = User::factory()->withTwoFactor()->create();
    $supplyHead->assignRole('Supply Head');

    $now = CarbonImmutable::now();

    // 1. Create a consumable product below reorder threshold
    $lowStockProduct = Product::factory()->consumable()->create([
        'sku' => 'SKU-ALERT-LOW',
        'reorder_threshold' => 10,
    ]);
    ProductStock::factory()->create([
        'product_id' => $lowStockProduct->id,
        'on_hand_qty' => 4,
    ]);

    // 2. Create a stock lot expiring in 3 days
    $expiringProduct = Product::factory()->consumable()->create(['sku' => 'SKU-ALERT-EXP']);
    ProductStock::factory()->create([
        'product_id' => $expiringProduct->id,
        'on_hand_qty' => 20,
    ]);
    $expiringLot = StockLot::factory()->create([
        'product_id' => $expiringProduct->id,
        'reference_no' => 'LOT-EXP-ALERT',
        'qty_remaining' => 20,
        'expires_at' => $now->addDays(3)->toDateString(),
    ]);

    // Run the command
    $this->artisan('app:inventory-generate-alerts')->assertSuccessful();

    // Verify low stock notification dispatched to Supply Head
    Notification::assertSentTo(
        $supplyHead,
        LowStockAlertNotification::class,
        function (LowStockAlertNotification $notification) use ($lowStockProduct) {
            return $notification->product->id === $lowStockProduct->id
                && $notification->currentStock === 4;
        }
    );

    // Verify expiring stock notification dispatched to Supply Head
    Notification::assertSentTo(
        $supplyHead,
        ExpiringStockAlertNotification::class,
        function (ExpiringStockAlertNotification $notification) use ($expiringLot) {
            return $notification->lot->id === $expiringLot->id
                && $notification->daysUntilExpiration === 3;
        }
    );

    // Run a second time: no new notifications should be dispatched because alerts are already created
    Notification::fake();
    $this->artisan('app:inventory-generate-alerts')->assertSuccessful();
    Notification::assertNothingSent();
});
