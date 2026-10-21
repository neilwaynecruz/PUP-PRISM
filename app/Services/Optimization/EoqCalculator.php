<?php

namespace App\Services\Optimization;

use App\Models\Product;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class EoqCalculator
{
    /**
     * Calculate Economic Order Quantity (EOQ) and optimal ordering parameters.
     *
     * Formula:
     * EOQ = sqrt((2 * D * S) / H)
     *
     * D = Annual Demand (units)
     * S = Fixed Ordering Cost per PO (default: 500.00 PHP)
     * H = Annual Holding Cost per unit = unit_price * holding_cost_rate (default: 20% of unit price)
     *
     * @param  Product  $product  Consumable product
     * @param  float  $orderingCost  Fixed cost to process a purchase order
     * @param  float  $holdingCostRate  Annual holding cost percentage (e.g., 0.20 for 20%)
     * @param  CarbonImmutable|null  $asOf  Reference date
     * @return array{
     *     annual_demand: int,
     *     unit_price: float,
     *     ordering_cost: float,
     *     holding_cost_rate: float,
     *     holding_cost_per_unit: float,
     *     eoq: int,
     *     orders_per_year: float,
     *     order_cycle_days: int,
     *     annual_ordering_cost: float,
     *     annual_holding_cost: float,
     *     total_annual_inventory_cost: float,
     * }
     */
    public function calculate(
        Product $product,
        float $orderingCost = 500.0,
        float $holdingCostRate = 0.20,
        ?CarbonImmutable $asOf = null,
    ): array {
        $asOf ??= CarbonImmutable::now()->endOfDay();
        $oneYearAgo = $asOf->subYear()->startOfDay();

        // Calculate actual units issued over the past year
        $annualDemand = (int) StockMovement::query()
            ->where('product_id', $product->id)
            ->where('movement_type', 'issue')
            ->whereBetween('performed_at', [$oneYearAgo, $asOf])
            ->sum(DB::raw('ABS(qty_delta)'));

        $unitPrice = round((float) ($product->unit_price ?? 0), 2);
        $holdingCostPerUnit = round($unitPrice * $holdingCostRate, 2);

        // Standard EOQ computation
        if ($annualDemand > 0 && $holdingCostPerUnit > 0 && $orderingCost > 0) {
            $rawEoq = sqrt((2 * $annualDemand * $orderingCost) / $holdingCostPerUnit);
            $eoq = (int) ceil($rawEoq);
            $ordersPerYear = round($annualDemand / max(1, $eoq), 1);
            $orderCycleDays = (int) round(365 / max(0.1, $ordersPerYear));
            $annualOrderingCost = round(($annualDemand / $eoq) * $orderingCost, 2);
            $annualHoldingCost = round(($eoq / 2) * $holdingCostPerUnit, 2);
            $totalCost = round($annualOrderingCost + $annualHoldingCost, 2);
        } else {
            $eoq = 0;
            $ordersPerYear = 0.0;
            $orderCycleDays = 0;
            $annualOrderingCost = 0.0;
            $annualHoldingCost = 0.0;
            $totalCost = 0.0;
        }

        return [
            'annual_demand' => $annualDemand,
            'unit_price' => $unitPrice,
            'ordering_cost' => $orderingCost,
            'holding_cost_rate' => $holdingCostRate,
            'holding_cost_per_unit' => $holdingCostPerUnit,
            'eoq' => $eoq,
            'orders_per_year' => $ordersPerYear,
            'order_cycle_days' => $orderCycleDays,
            'annual_ordering_cost' => $annualOrderingCost,
            'annual_holding_cost' => $annualHoldingCost,
            'total_annual_inventory_cost' => $totalCost,
        ];
    }
}
