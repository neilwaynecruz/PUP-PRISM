<?php

namespace App\Services\Optimization;

use App\Enums\ProductType;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class InventoryOptimizationService
{
    public function __construct(
        private readonly AbcAnalyzer $abcAnalyzer,
        private readonly SafetyStockCalculator $safetyStockCalculator,
        private readonly EoqCalculator $eoqCalculator,
        private readonly InventoryOptimizationCache $cache,
    ) {}

    /**
     * Get the full ABC classification analysis and summary.
     *
     * @return array{
     *     items: list<array{
     *         product_id: int,
     *         product_name: string,
     *         sku: string,
     *         unit_price: float,
     *         annual_consumption: int,
     *         annual_value: float,
     *         cumulative_value: float,
     *         cumulative_percentage: float,
     *         classification: string,
     *     }>,
     *     summary: array{
     *         total_products: int,
     *         total_annual_value: float,
     *         class_a_count: int,
     *         class_b_count: int,
     *         class_c_count: int,
     *         class_a_value: float,
     *         class_b_value: float,
     *         class_c_value: float,
     *         class_a_percentage: float,
     *         class_b_percentage: float,
     *         class_c_percentage: float,
     *     }
     * }
     */
    public function getAbcAnalysis(?CarbonImmutable $asOf = null): array
    {
        $effectiveAsOf = $asOf ?? CarbonImmutable::now()->endOfDay();

        return $this->cache->rememberAbc(
            $effectiveAsOf->format('Y-m-d_H:i:s'),
            365,
            fn (): array => $this->abcAnalyzer->analyze($effectiveAsOf),
        );
    }

    /**
     * Get complete optimization profile for a product.
     *
     * @return array{
     *     product_id: int,
     *     sku: string,
     *     name: string,
     *     on_hand_qty: int,
     *     abc_classification: string,
     *     safety_stock: array{
     *         mean_daily_demand: float,
     *         demand_std_dev: float,
     *         lead_time_days: int,
     *         service_level_z: float,
     *         safety_stock: int,
     *         reorder_point: int,
     *         lookback_days: int,
     *         data_points_count: int,
     *         non_zero_days_count: int,
     *     },
     *     eoq: array{
     *         annual_demand: int,
     *         unit_price: float,
     *         ordering_cost: float,
     *         holding_cost_rate: float,
     *         holding_cost_per_unit: float,
     *         eoq: int,
     *         orders_per_year: float,
     *         order_cycle_days: int,
     *         annual_ordering_cost: float,
     *         annual_holding_cost: float,
     *         total_annual_inventory_cost: float,
     *     },
     *     recommended_batch_order_qty: int,
     * }
     */
    public function getProductOptimization(Product $product, ?CarbonImmutable $asOf = null): array
    {
        $effectiveAsOf = $asOf ?? CarbonImmutable::now()->endOfDay();

        return $this->cache->rememberProductOptimization(
            $product->id,
            $effectiveAsOf->format('Y-m-d_H:i:s'),
            1.65,
            500.0,
            0.20,
            function () use ($product, $effectiveAsOf): array {
                $product->loadMissing(['stock:id,product_id,on_hand_qty', 'forecastProfile']);

                $abcAnalysis = $this->getAbcAnalysis($effectiveAsOf);
                $classification = $this->abcAnalyzer->extractClassificationFromAnalysis($abcAnalysis, $product->id);
                $safetyStock = $this->safetyStockCalculator->calculate($product, asOf: $effectiveAsOf);
                $eoq = $this->eoqCalculator->calculate($product, asOf: $effectiveAsOf);

                return $this->assembleProductOptimization($product, $classification, $safetyStock, $eoq);
            },
        );
    }

    /**
     * Get complete optimization profiles for a collection of products using batched DB queries.
     *
     * @param  Collection<int, Product>  $products
     * @param  array{items: list<array<string, mixed>>}|null  $precomputedAbc
     * @return array<int, array<string, mixed>>
     */
    public function getBatchProductOptimizations(
        Collection $products,
        ?CarbonImmutable $asOf = null,
        ?array $precomputedAbc = null,
    ): array {
        if ($products->isEmpty()) {
            return [];
        }

        $effectiveAsOf = $asOf ?? CarbonImmutable::now()->endOfDay();
        $products->loadMissing(['stock:id,product_id,on_hand_qty', 'forecastProfile']);

        $abcAnalysis = $precomputedAbc ?? $this->getAbcAnalysis($effectiveAsOf);
        $abcClassByProductId = [];

        foreach ($abcAnalysis['items'] as $item) {
            if (isset($item['product_id'])) {
                $abcClassByProductId[(int) $item['product_id']] = (string) ($item['classification'] ?? 'C');
            }
        }

        $safetyStockByProductId = $this->safetyStockCalculator->calculateBatch($products, asOf: $effectiveAsOf);
        $eoqByProductId = $this->eoqCalculator->calculateBatch($products, asOf: $effectiveAsOf);

        $results = [];

        foreach ($products as $product) {
            $classification = $abcClassByProductId[$product->id] ?? 'C';
            $safetyStock = $safetyStockByProductId[$product->id] ?? $this->safetyStockCalculator->calculateFromDailyData($product, []);
            $eoq = $eoqByProductId[$product->id] ?? $this->eoqCalculator->calculateFromAnnualDemand($product, 0);

            $results[$product->id] = $this->assembleProductOptimization($product, $classification, $safetyStock, $eoq);
        }

        return $results;
    }

    /**
     * Get aggregate optimization summary for dashboard widgets.
     *
     * @return array<string, mixed>
     */
    public function getOptimizationSummary(?CarbonImmutable $asOf = null): array
    {
        $abc = $this->getAbcAnalysis($asOf);

        $consumablesCount = Product::query()
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->count();

        return [
            'total_active_consumables' => $consumablesCount,
            'total_consumption_value' => $abc['summary']['total_annual_value'],
            'abc_summary' => $abc['summary'],
        ];
    }

    /**
     * @param  array<string, mixed>  $safetyStock
     * @param  array<string, mixed>  $eoq
     * @return array{
     *     product_id: int,
     *     sku: string,
     *     name: string,
     *     on_hand_qty: int,
     *     abc_classification: string,
     *     safety_stock: array<string, mixed>,
     *     eoq: array<string, mixed>,
     *     recommended_batch_order_qty: int,
     * }
     */
    private function assembleProductOptimization(
        Product $product,
        string $classification,
        array $safetyStock,
        array $eoq,
    ): array {
        // Suggested batch order size: EOQ if available and positive, else fallback to safety stock + lead demand
        $batchSize = $eoq['eoq'] > 0
            ? $eoq['eoq']
            : max(1, (int) $safetyStock['reorder_point']);

        return [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'on_hand_qty' => (int) ($product->stock->on_hand_qty ?? 0),
            'abc_classification' => $classification,
            'safety_stock' => $safetyStock,
            'eoq' => $eoq,
            'recommended_batch_order_qty' => $batchSize,
        ];
    }
}
