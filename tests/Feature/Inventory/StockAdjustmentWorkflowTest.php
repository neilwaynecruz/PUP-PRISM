<?php

use App\Enums\StockMovementReasonCode;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

test('admin can record a negative stock adjustment with reason code and lot impact', function () {
    $position = Position::factory()->create();
    $csrfToken = 'stock-adjustment-token';

    $admin = User::factory()->withTwoFactor()->assignedPosition($position)->create();
    $admin->assignRole('Admin');

    $product = Product::factory()->consumable()->create(['sku' => 'SKU-ADJUST-001']);
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 10]);

    $lot = StockLot::factory()->create([
        'product_id' => $product->id,
        'qty_received' => 10,
        'qty_remaining' => 10,
        'received_at' => CarbonImmutable::now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.movements.adjust', absolute: false), [
            '_token' => $csrfToken,
            'product_id' => $product->id,
            'qty_delta' => -3,
            'reason_code' => StockMovementReasonCode::Shrinkage->value,
            'notes' => 'Cycle breakage confirmed.',
        ])
        ->assertRedirect();

    expect(ProductStock::query()->where('product_id', $product->id)->firstOrFail()->on_hand_qty)->toBe(7);
    expect($lot->fresh()?->qty_remaining)->toBe(7);

    $movement = StockMovement::query()
        ->where('product_id', $product->id)
        ->where('movement_type', 'adjustment')
        ->latest('id')
        ->firstOrFail();

    expect($movement->reason_code)->toBe(StockMovementReasonCode::Shrinkage->value);
    expect($movement->qty_delta)->toBe(-3);
    expect($movement->qty_before)->toBe(10);
    expect($movement->qty_after)->toBe(7);
});

test('admin can record a cycle count variance that increases stock and preserves audit metadata', function () {
    $position = Position::factory()->create();
    $csrfToken = 'cycle-count-token';

    $admin = User::factory()->withTwoFactor()->assignedPosition($position)->create();
    $admin->assignRole('Admin');

    $product = Product::factory()->consumable()->create(['sku' => 'SKU-COUNT-001']);
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 8]);

    StockLot::factory()->create([
        'product_id' => $product->id,
        'qty_received' => 8,
        'qty_remaining' => 8,
        'received_at' => CarbonImmutable::now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.movements.cycle-count', absolute: false), [
            '_token' => $csrfToken,
            'product_id' => $product->id,
            'counted_qty' => 10,
            'reason_code' => StockMovementReasonCode::RoutineCount->value,
            'notes' => 'Bin count matched physical stock on the shelf.',
        ])
        ->assertRedirect();

    expect(ProductStock::query()->where('product_id', $product->id)->firstOrFail()->on_hand_qty)->toBe(10);

    $movement = StockMovement::query()
        ->where('product_id', $product->id)
        ->where('movement_type', 'cycle_count')
        ->latest('id')
        ->firstOrFail();

    expect($movement->reason_code)->toBe(StockMovementReasonCode::RoutineCount->value);
    expect($movement->counted_qty)->toBe(10);
    expect($movement->variance_qty)->toBe(2);
    expect($movement->qty_after)->toBe(10);
});
