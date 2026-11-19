<?php

use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('stock movement constraint migration can round-trip postgresql movement types', function () {
    withPostgresTestingDatabase(function (): void {
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
});

test('stock movement adjustment migration rollback normalizes adjustment and cycle count rows', function () {
    withPostgresTestingDatabase(function (): void {
        $user = User::factory()->create();

        $adjustmentMovement = StockMovement::factory()->create([
            'movement_type' => 'adjustment',
            'reason_code' => 'reconciliation',
            'counted_qty' => 12,
            'variance_qty' => 2,
            'performed_by' => $user->id,
        ]);

        $cycleCountMovement = StockMovement::factory()->create([
            'movement_type' => 'cycle_count',
            'counted_qty' => 5,
            'variance_qty' => 0,
            'performed_by' => $user->id,
        ]);

        $migration = require base_path('database/migrations/2026_07_04_025311_add_adjustment_fields_to_stock_movements_table.php');

        $migration->down();

        expect($adjustmentMovement->fresh()?->movement_type)->toBe('transfer')
            ->and($cycleCountMovement->fresh()?->movement_type)->toBe('transfer')
            ->and(Schema::hasColumn('stock_movements', 'reason_code'))->toBeFalse();

        $migration->up();

        $adjustmentMovement->refresh();
        $cycleCountMovement->refresh();

        $adjustmentMovement->update(['movement_type' => 'adjustment']);
        $cycleCountMovement->update(['movement_type' => 'cycle_count']);

        expect(Schema::hasColumn('stock_movements', 'reason_code'))->toBeTrue()
            ->and($adjustmentMovement->fresh()?->movement_type)->toBe('adjustment')
            ->and($cycleCountMovement->fresh()?->movement_type)->toBe('cycle_count');
    });
});
