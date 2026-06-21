<?php

use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('stock movement constraint migration can round-trip postgresql movement types', function () {
    if (Schema::getConnection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('This test covers the PostgreSQL-only stock movement constraint migration.');
    }

    $user = User::factory()->create();

    $transferMovement = StockMovement::factory()->create([
        'movement_type' => 'transfer',
        'performed_by' => $user->id,
    ]);

    $condemnMovement = StockMovement::factory()->create([
        'movement_type' => 'condemn',
        'performed_by' => $user->id,
    ]);

    $migration = require base_path('database/migrations/2026_04_15_085123_update_stock_movements_movement_type_constraint.php');

    $migration->down();

    expect($transferMovement->fresh()?->movement_type)->toBe('adjust');
    expect($condemnMovement->fresh()?->movement_type)->toBe('damage');

    $migration->up();

    expect($transferMovement->fresh()?->movement_type)->toBe('transfer');
    expect($condemnMovement->fresh()?->movement_type)->toBe('condemn');
});
