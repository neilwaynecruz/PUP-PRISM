<?php

namespace App\Services\Forecasting;

use App\Models\Product;
use App\Services\Forecasting\Methods\ExponentialSmoothingMethod;
use App\Services\Forecasting\Methods\MovingAverageMethod;
use App\Services\Forecasting\Methods\SeasonalMethod;
use Carbon\CarbonImmutable;

class ForecastBacktester
{
    public function __construct(
        private readonly ConsumptionDataCollector $collector,
        private readonly MovingAverageMethod $movingAverage,
        private readonly ExponentialSmoothingMethod $exponentialSmoothing,
        private readonly SeasonalMethod $seasonalMethod,
    ) {}

    /**
     * Backtest and compare forecasting methods on historical consumption data.
     *
     * @return array{
     *     has_sufficient_data: bool,
     *     evaluation_days: int,
     *     total_history_days: int,
     *     non_zero_history_days: int,
     *     methods: array<string, array{
     *         label: string,
     *         mae: float,
     *         rmse: float,
     *         wape_pct: float|null,
     *         bias: float,
     *     }>,
     *     recommended_method: string|null,
     * }
     */
    public function evaluate(
        Product $product,
        int $evaluationDays = 14,
        int $totalLookbackDays = 60,
        ?CarbonImmutable $asOf = null,
    ): array {
        $asOf ??= CarbonImmutable::now()->endOfDay();
        $dailyData = $this->collector->getDailyConsumption($product, $totalLookbackDays, $asOf);
        $totalDays = count($dailyData);

        $nonZeroDays = count(array_filter($dailyData, fn (array $point) => $point['qty'] > 0));

        // Need at least 14 days total and some activity to perform a meaningful split
        if ($totalDays < 21 || $nonZeroDays < 3) {
            return [
                'has_sufficient_data' => false,
                'evaluation_days' => $evaluationDays,
                'total_history_days' => $totalDays,
                'non_zero_history_days' => $nonZeroDays,
                'methods' => [],
                'recommended_method' => null,
            ];
        }

        $evaluationDays = min($evaluationDays, (int) floor($totalDays / 2));
        $trainingCount = $totalDays - $evaluationDays;

        /** @var list<array{date: string, qty: int}> $trainingData */
        $trainingData = array_slice($dailyData, 0, $trainingCount);
        /** @var list<array{date: string, qty: int}> $evaluationData */
        $evaluationData = array_slice($dailyData, $trainingCount);

        $methodDefinitions = [
            'exponential_smoothing' => [
                'label' => 'Exponential Smoothing',
                'predictions' => $this->exponentialSmoothing->forecast($trainingData, $evaluationDays),
            ],
            'moving_average' => [
                'label' => 'Moving Average',
                'predictions' => $this->movingAverage->forecast($trainingData, $evaluationDays),
            ],
            'seasonal' => [
                'label' => 'Seasonal Pattern',
                'predictions' => $this->seasonalMethod->forecast($trainingData, $evaluationDays),
            ],
        ];

        $results = [];
        $actualsByDate = [];
        $totalActualQty = 0;

        foreach ($evaluationData as $point) {
            $actualsByDate[$point['date']] = (float) $point['qty'];
            $totalActualQty += $point['qty'];
        }

        foreach ($methodDefinitions as $key => $def) {
            $predictions = $def['predictions'];
            $absoluteErrors = [];
            $squaredErrors = [];
            $errors = [];

            foreach ($predictions as $prediction) {
                $date = $prediction['date'];
                if (! array_key_exists($date, $actualsByDate)) {
                    continue;
                }

                $actual = $actualsByDate[$date];
                $predicted = (float) $prediction['predicted_qty'];
                $error = $predicted - $actual;

                $errors[] = $error;
                $absoluteErrors[] = abs($error);
                $squaredErrors[] = $error ** 2;
            }

            $count = max(1, count($absoluteErrors));
            $mae = round(array_sum($absoluteErrors) / $count, 2);
            $rmse = round(sqrt(array_sum($squaredErrors) / $count), 2);
            $bias = round(array_sum($errors) / $count, 2);

            // WAPE = sum(|Actual - Forecast|) / sum(Actual)
            $wapePct = $totalActualQty > 0
                ? round((array_sum($absoluteErrors) / $totalActualQty) * 100, 1)
                : null;

            $results[$key] = [
                'label' => $def['label'],
                'mae' => $mae,
                'rmse' => $rmse,
                'wape_pct' => $wapePct,
                'bias' => $bias,
            ];
        }

        // Recommend method with lowest MAE
        $recommendedMethod = null;
        $lowestMae = PHP_FLOAT_MAX;

        foreach ($results as $key => $metrics) {
            if ($metrics['mae'] < $lowestMae) {
                $lowestMae = $metrics['mae'];
                $recommendedMethod = $key;
            }
        }

        return [
            'has_sufficient_data' => true,
            'evaluation_days' => $evaluationDays,
            'total_history_days' => $totalDays,
            'non_zero_history_days' => $nonZeroDays,
            'methods' => $results,
            'recommended_method' => $recommendedMethod,
        ];
    }
}
