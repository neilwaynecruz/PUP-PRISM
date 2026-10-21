<?php

namespace App\Services\Optimization;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AbcAnalyzer
{
    /**
     * Run ABC classification analysis on all active consumable products.
     *
     * Class A: Top 70% of total consumption value (high value, tight control)
     * Class B: Next 20% of total consumption value (moderate value)
     * Class C: Bottom 10% of total consumption value (low value)
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
    public function analyze(?CarbonImmutable $asOf = null, int $lookbackDays = 365): array
    {
        $asOf ??= CarbonImmutable::now()->endOfDay();
        $since = $asOf->subDays($lookbackDays)->startOfDay();

        // 1. Gather total issued quantity for each consumable product in the lookback period
        /** @var Collection<int, int> $consumptionByProduct */
        $consumptionByProduct = StockMovement::query()
            ->where('movement_type', 'issue')
            ->whereBetween('performed_at', [$since, $asOf])
            ->select('product_id', DB::raw('CAST(SUM(ABS(qty_delta)) AS INTEGER) as total_issued'))
            ->groupBy('product_id')
            ->pluck('total_issued', 'product_id');

        // 2. Load all active consumable products
        $products = Product::query()
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->with('stock:id,product_id,on_hand_qty')
            ->get(['id', 'sku', 'name', 'unit_price']);

        // 3. Map products with annual consumption and value
        $evaluated = $products->map(function (Product $product) use ($consumptionByProduct): array {
            $annualQty = (int) ($consumptionByProduct->get($product->id) ?? 0);
            $unitPrice = round((float) ($product->unit_price ?? 0), 2);
            $annualValue = round($annualQty * $unitPrice, 2);

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'unit_price' => $unitPrice,
                'annual_consumption' => $annualQty,
                'annual_value' => $annualValue,
            ];
        })->sortByDesc('annual_value')->values();

        $totalAnnualValue = round((float) $evaluated->sum('annual_value'), 2);
        $runningCumulativeValue = 0.0;

        $classifiedItems = [];
        $countA = 0;
        $countB = 0;
        $countC = 0;
        $valueA = 0.0;
        $valueB = 0.0;
        $valueC = 0.0;

        foreach ($evaluated as $item) {
            $priorCumulativePct = $totalAnnualValue > 0
                ? round(($runningCumulativeValue / $totalAnnualValue) * 100, 2)
                : 0.0;

            $runningCumulativeValue += $item['annual_value'];
            $cumulativePct = $totalAnnualValue > 0
                ? round(($runningCumulativeValue / $totalAnnualValue) * 100, 2)
                : 100.0;

            // ABC thresholds:
            // Items whose accumulation begins within the top 70% of value are Class A
            // Items whose accumulation begins within 70%-90% of value are Class B
            // The remaining items are Class C
            $classification = match (true) {
                $totalAnnualValue <= 0 => 'C',
                $classifiedItems === [] || $priorCumulativePct < 70.0 => 'A',
                $priorCumulativePct < 90.0 => 'B',
                default => 'C',
            };

            match ($classification) {
                'A' => [$countA++, $valueA += $item['annual_value']],
                'B' => [$countB++, $valueB += $item['annual_value']],
                default => [$countC++, $valueC += $item['annual_value']],
            };

            $classifiedItems[] = [
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'sku' => $item['sku'],
                'unit_price' => $item['unit_price'],
                'annual_consumption' => $item['annual_consumption'],
                'annual_value' => $item['annual_value'],
                'cumulative_value' => round($runningCumulativeValue, 2),
                'cumulative_percentage' => $cumulativePct,
                'classification' => $classification,
            ];
        }

        $totalProducts = count($classifiedItems);

        return [
            'items' => $classifiedItems,
            'summary' => [
                'total_products' => $totalProducts,
                'total_annual_value' => $totalAnnualValue,
                'class_a_count' => $countA,
                'class_b_count' => $countB,
                'class_c_count' => $countC,
                'class_a_value' => round($valueA, 2),
                'class_b_value' => round($valueB, 2),
                'class_c_value' => round($valueC, 2),
                'class_a_percentage' => $totalAnnualValue > 0 ? round(($valueA / $totalAnnualValue) * 100, 1) : 0.0,
                'class_b_percentage' => $totalAnnualValue > 0 ? round(($valueB / $totalAnnualValue) * 100, 1) : 0.0,
                'class_c_percentage' => $totalAnnualValue > 0 ? round(($valueC / $totalAnnualValue) * 100, 1) : 0.0,
            ],
        ];
    }

    /**
     * Get the ABC classification for a single product.
     */
    public function classifyProduct(Product $product, ?CarbonImmutable $asOf = null): string
    {
        $analysis = $this->analyze($asOf);

        foreach ($analysis['items'] as $item) {
            if ($item['product_id'] === $product->id) {
                return $item['classification'];
            }
        }

        return 'C';
    }
}
