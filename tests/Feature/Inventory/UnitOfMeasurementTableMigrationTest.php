<?php

use App\Models\Product;
use App\Models\UnitOfMeasurement;
use Illuminate\Support\Facades\Schema;

function normalizeUnitTableMigration(): object
{
    return require database_path('migrations/2026_10_31_221029_normalize_unit_of_measurements_table_name.php');
}

test('a database built from the draft units table converges on the conventional name', function () {
    $unit = UnitOfMeasurement::query()->create(['code' => 'ream', 'name' => 'Ream', 'abbreviation' => 'ream']);
    $product = Product::factory()->consumable()->create(['unit_of_measurement_id' => $unit->id]);

    // Simulate a database migrated with the earlier draft table name.
    Schema::rename('unit_of_measurements', 'units_of_measurement');

    normalizeUnitTableMigration()->up();

    expect(Schema::hasTable('unit_of_measurements'))->toBeTrue()
        ->and(Schema::hasTable('units_of_measurement'))->toBeFalse()
        ->and(UnitOfMeasurement::query()->whereKey($unit->id)->value('name'))->toBe('Ream')
        ->and($product->fresh()->unitOfMeasurement?->code)->toBe('ream');
});

test('the normalization is a no-op on databases that already use the conventional name', function () {
    UnitOfMeasurement::query()->create(['code' => 'box', 'name' => 'Box', 'abbreviation' => 'box']);

    normalizeUnitTableMigration()->up();

    expect(Schema::hasTable('unit_of_measurements'))->toBeTrue()
        ->and(UnitOfMeasurement::query()->where('code', 'box')->exists())->toBeTrue();
});
