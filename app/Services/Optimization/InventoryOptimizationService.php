<?php

namespace App\Services\Optimization;

use App\Enums\ProductType;
use App\Models\Product;
use Carbon\CarbonImmutable;

class InventoryOptimizationService
{
    public function __construct(
        private readonly AbcAnalyzer $abcAnalyzer,
        private readonly SafetyStockCalculator $safetyStockCalculator,
        private readonly EoqCalculator $eoqCalculator,
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
        return $this->abcAnalyzer->analyze($asOf);
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
        $asOf ??= CarbonImmutable::now()->endOfDay();

        $product->loadMissing(['stock:id,product_id,on_hand_qty', 'forecastProfile']);

        $classification = $this->abcAnalyzer->classifyProduct($product, $asOf);
        $safetyStock = $this->safetyStockCalculator->calculate($product, asOf: $asOf);
        $eoq = $this->eoqCalculator->calculate($product, asOf: $asOf);

        // Suggested batch order size: EOQ if available and positive, else fallback to safety stock + lead demand
        $batchSize = $eoq['eoq'] > 0
            ? $eoq['eoq']
            : max(1, $safetyStock['reorder_point']);

        return [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'on_hand_qty' => (int) ($product->stock?->on_hand_qty ?? 0),
            'abc_classification' => $classification,
            'safety_stock' => $safetyStock,
            'eoq' => $eoq,
            'recommended_batch_order_qty' => $batchSize,
        ];
    }

    /**
     * Get aggregate optimization summary for dashboard widgets.
     *
     * @return array<string, mixed>
     */
    public function getOptimizationSummary(?CarbonImmutable $asOf = null): array
    {
        $abc = $this->abcAnalyzer->analyze($asOf);

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
}
