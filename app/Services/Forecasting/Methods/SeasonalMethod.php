<?php

namespace App\Services\Forecasting\Methods;

use Carbon\CarbonImmutable;

class SeasonalMethod
{
    /**
     * @param  list<array{date: string, qty: int}>  $dailyData
     * @return list<array{date: string, predicted_qty: float}>
     */
    public function forecast(array $dailyData, int $horizonDays = 30): array
    {
        if ($dailyData === []) {
            return [];
        }

        $recentValues = array_slice(array_column($dailyData, 'qty'), -28);
        $recentAverage = $recentValues === [] ? 0.0 : array_sum($recentValues) / count($recentValues);

        $weekdayBuckets = [];

        foreach ($dailyData as $point) {
            $weekday = CarbonImmutable::parse($point['date'])->dayOfWeekIso;
            $weekdayBuckets[$weekday][] = $point['qty'];
        }

        $weekdayAverages = [];

        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $bucket = $weekdayBuckets[$weekday] ?? [];
            $weekdayAverages[$weekday] = $bucket === []
                ? $recentAverage
                : array_sum($bucket) / count($bucket);
        }

        $lastDate = CarbonImmutable::parse($dailyData[array_key_last($dailyData)]['date']);
        $predictions = [];

        for ($day = 1; $day <= $horizonDays; $day++) {
            $predictionDate = $lastDate->addDays($day);
            $weekdayAverage = $weekdayAverages[$predictionDate->dayOfWeekIso] ?? $recentAverage;
            $blendedAverage = ($weekdayAverage * 0.65) + ($recentAverage * 0.35);

            $predictions[] = [
                'date' => $predictionDate->toDateString(),
                'predicted_qty' => round(max(0, $blendedAverage), 2),
            ];
        }

        return $predictions;
    }
}
