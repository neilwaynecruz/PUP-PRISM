<?php

use App\Models\Product;
use App\Models\User;
use App\Services\GlobalSearchService;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

/**
 * PostgreSQL compares LIKE patterns case-sensitively, while the default SQLite
 * test database does not. These tests keep searches case-insensitive on both.
 */
test('application searches never use the case-sensitive like operator', function () {
    $offenders = collect(File::allFiles(app_path()))
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
        // Reference-number sequencing intentionally matches an exact, upper-case prefix.
        ->reject(fn (SplFileInfo $file) => str_ends_with($file->getPathname(), 'PurchaseOrderNumberGenerator.php'))
        ->flatMap(function (SplFileInfo $file): array {
            $matches = [];

            foreach (file($file->getPathname()) as $number => $line) {
                if (preg_match("/,\s*'like'\s*,/i", $line) === 1) {
                    $matches[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).':'.($number + 1);
                }
            }

            return $matches;
        })
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

test('global search matches records regardless of letter case on postgresql', function () {
    withPostgresTestingDatabase(function (): void {
        Role::findOrCreate('Admin');
        $admin = User::factory()->withTwoFactor()->create();
        $admin->assignRole('Admin');
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $product = Product::factory()->consumable()->create([
            'name' => "Whiteboard Marker Case {$suffix}",
            'sku' => "SKU-CASE-{$suffix}",
        ]);

        $byName = collect(app(GlobalSearchService::class)->search($admin, mb_strtolower("marker case {$suffix}")));
        $bySku = collect(app(GlobalSearchService::class)->search($admin, mb_strtolower("sku-case-{$suffix}")));

        expect($byName->where('type', 'product')->pluck('id')->all())->toContain($product->id)
            ->and($bySku->where('type', 'product')->pluck('id')->all())->toContain($product->id);
    });
});
