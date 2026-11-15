<?php

namespace App\Services\Reports;

use App\Enums\ProductType;
use App\Models\Product;
use App\Services\Optimization\InventoryOptimizationService;
use App\Services\Reports\Support\TableReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class InventoryOptimizationReportService extends AbstractTableReportService
{
    public function __construct(
        private readonly InventoryOptimizationService $optimizationService,
    ) {}

    public function build(Request $request): TableReport
    {
        $today = CarbonImmutable::now()->endOfDay();
        $classFilter = $request->string('classification')->trim()->upper()->toString();

        $abcAnalysis = $this->optimizationService->getAbcAnalysis($today);
        $abcByProductId = collect($abcAnalysis['items'])->keyBy('product_id');

        $products = Product::query()
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->with(['stock:id,product_id,on_hand_qty', 'forecastProfile'])
            ->orderBy('name')
            ->get();

        $filteredProducts = $classFilter !== ''
            ? $products->filter(function (Product $product) use ($abcByProductId, $classFilter): bool {
                $abcItem = $abcByProductId->get($product->id);

                return ($abcItem['classification'] ?? 'C') === $classFilter;
            })->values()
            : $products;

        $optimizations = $this->optimizationService->getBatchProductOptimizations(
            $filteredProducts,
            $today,
            $abcAnalysis,
        );

        $rows = $filteredProducts
            ->map(function (Product $product) use ($abcByProductId, $optimizations): array {
                $abcItem = $abcByProductId->get($product->id);
                $classification = $abcItem['classification'] ?? 'C';
                $annualConsumption = $abcItem['annual_consumption'] ?? 0;
                $annualValue = $abcItem['annual_value'] ?? 0.0;
                $unitPrice = round((float) ($product->unit_price ?? 0), 2);
                $currentStock = (int) ($product->stock?->on_hand_qty ?? 0);

                $profile = $optimizations[$product->id];

                return [
                    'sku' => $product->sku,
                    'product_name' => $product->name,
                    'unit_price' => number_format($unitPrice, 2),
                    'annual_consumption' => $annualConsumption,
                    'annual_value' => number_format($annualValue, 2),
                    'abc_class' => $classification,
                    'current_stock' => $currentStock,
                    'safety_stock' => $profile['safety_stock']['safety_stock'],
                    'reorder_point' => $profile['safety_stock']['reorder_point'],
                    'eoq' => $profile['eoq']['eoq'],
                ];
            })
            ->values()
            ->all();

        return new TableReport(
            title: 'Inventory Optimization & ABC Analysis Report',
            filenameBase: 'inventory-optimization-report',
            filters: $this->normalizeFilters([
                'ABC classification' => $classFilter !== '' ? "Class {$classFilter}" : 'All classes (A, B, C)',
                'Criteria' => 'Active consumables evaluated on consumption spend, safety stock, and EOQ',
            ]),
            columns: [
                'sku' => 'SKU',
                'product_name' => 'Product name',
                'unit_price' => 'Unit price (PHP)',
                'annual_consumption' => 'Annual usage',
                'annual_value' => 'Annual spend (PHP)',
                'abc_class' => 'ABC class',
                'current_stock' => 'Current stock',
                'safety_stock' => 'Safety stock',
                'reorder_point' => 'Reorder point',
                'eoq' => 'EOQ (units)',
            ],
            rows: $rows,
            generatedBy: $this->generatedBy($request),
            generatedAt: CarbonImmutable::now(),
        );
    }
}
