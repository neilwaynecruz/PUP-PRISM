<?php

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Reports\CsvTableExporter;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
    $this->withoutVite();
});

test('csv exporter neutralizes formula injection triggers', function () {
    $exporter = new CsvTableExporter;

    // Direct formula triggers
    expect($exporter->sanitizeCellValue('=cmd|/C calc!A0'))->toBe("'=cmd|/C calc!A0")
        ->and($exporter->sanitizeCellValue('=1+1'))->toBe("'=1+1")
        ->and($exporter->sanitizeCellValue('+1+2'))->toBe("'+1+2")
        ->and($exporter->sanitizeCellValue('-2+3+cmd!A0'))->toBe("'-2+3+cmd!A0")
        ->and($exporter->sanitizeCellValue('@SUM(A1:A10)'))->toBe("'@SUM(A1:A10)");

    // Hidden behind leading whitespace or control characters
    expect($exporter->sanitizeCellValue('   =1+1'))->toBe("'   =1+1")
        ->and($exporter->sanitizeCellValue("\t@SUM(1+1)"))->toBe("'\t@SUM(1+1)")
        ->and($exporter->sanitizeCellValue("\n=2+2"))->toBe("'\n=2+2");
});

test('csv exporter preserves legitimate negative numbers and numeric strings', function () {
    $exporter = new CsvTableExporter;

    expect($exporter->sanitizeCellValue(-5))->toBe('-5')
        ->and($exporter->sanitizeCellValue('-5'))->toBe('-5')
        ->and($exporter->sanitizeCellValue(-12.50))->toBe('-12.5')
        ->and($exporter->sanitizeCellValue('-12.50'))->toBe('-12.50')
        ->and($exporter->sanitizeCellValue('+50'))->toBe('+50')
        ->and($exporter->sanitizeCellValue(0))->toBe('0')
        ->and($exporter->sanitizeCellValue(42))->toBe('42');
});

test('csv exporter preserves unicode, quotes, and commas safely', function () {
    $exporter = new CsvTableExporter;

    expect($exporter->sanitizeCellValue('Normal text'))->toBe('Normal text')
        ->and($exporter->sanitizeCellValue('₱ 2,500.00'))->toBe('₱ 2,500.00')
        ->and($exporter->sanitizeCellValue('Product with "quotes" and, commas'))->toBe('Product with "quotes" and, commas');
});

test('product export escapes untrusted formula strings from database records', function () {
    $user = User::factory()->create();
    $user->assignRole('Property Custodian');

    Product::factory()->consumable()->create([
        'sku' => '=MALICIOUS-SKU',
        'name' => '+Injected Formula Name',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('inventory.reports.products', [
            'format' => 'csv',
        ], absolute: false));

    $response->assertOk();
    $content = $response->streamedContent();

    // Verify formula trigger is escaped with single quote
    expect($content)->toContain("'=MALICIOUS-SKU")
        ->and($content)->toContain("'+Injected Formula Name")
        ->not->toContain("\n=MALICIOUS-SKU")
        ->not->toContain("\n+Injected Formula Name");
});

test('stock movement export preserves negative quantity deltas while escaping malicious notes', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole('Admin');

    $product = Product::factory()->consumable()->create(['sku' => 'SKU-DELTA-TEST']);

    StockMovement::factory()->create([
        'product_id' => $product->id,
        'movement_type' => 'issue',
        'qty_delta' => -7,
        'performed_by' => $admin->id,
        'notes' => '@ExploitFormula(1,2)',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('inventory.reports.movements', [
            'format' => 'csv',
        ], absolute: false));

    $response->assertOk();
    $content = $response->streamedContent();

    // Negative delta preserved as number
    expect($content)->toContain('-7')
        // Notes escaped
        ->and($content)->toContain("'@ExploitFormula(1,2)");
});

test('empty report exports are handled gracefully', function () {
    $user = User::factory()->create();
    $user->assignRole('Property Custodian');

    $response = $this->actingAs($user)
        ->get(route('inventory.reports.products', [
            'format' => 'csv',
            'search' => 'NonExistentProductQuery12345',
        ], absolute: false));

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)->toContain('Product Inventory Listing')
        ->toContain('SKU');
});

test('unauthorized users are denied export access across all reporting endpoints', function () {
    $plainUser = User::factory()->create();

    $restrictedEndpoints = [
        ['route' => 'inventory.reports.products', 'role_needed' => 'Property Custodian/Admin'],
        ['route' => 'inventory.reports.movements', 'role_needed' => 'Admin'],
        ['route' => 'inventory.reports.asset-conditions', 'role_needed' => 'Admin'],
        ['route' => 'inventory.reports.bookings', 'role_needed' => 'Property Custodian/Admin'],
        ['route' => 'inventory.reports.requisitions', 'role_needed' => 'Property Custodian/Admin'],
        ['route' => 'inventory.reports.procurement', 'role_needed' => 'Supply Head/Admin'],
        ['route' => 'inventory.reports.forecasting', 'role_needed' => 'Supply Head/Admin'],
        ['route' => 'inventory.reports.slow-moving', 'role_needed' => 'Supply Head/Admin'],
        ['route' => 'inventory.reports.expirations', 'role_needed' => 'Supply Head/Admin'],
        ['route' => 'inventory.reports.optimization', 'role_needed' => 'Supply Head/Admin'],
    ];

    foreach ($restrictedEndpoints as $endpoint) {
        $this->actingAs($plainUser)
            ->get(route($endpoint['route'], ['format' => 'csv'], absolute: false))
            ->assertForbidden();
    }
});
