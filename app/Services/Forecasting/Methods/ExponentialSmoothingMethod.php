<?php

namespace App\Services\Forecasting\Methods;

use Carbon\CarbonImmutable;

class ExponentialSmoothingMethod
{
    /**
     * @param  list<array{date: string, qty: int}>  $dailyData
     * @return list<array{date: string, predicted_qty: float}>
     */
    public function forecast(
        array $dailyData,
        int $horizonDays = 30,
        float $alpha = 0.35,
        ?float $beta = 0.15,
    ): array {
        if ($dailyData === []) {
            return [];
        }

        $values = array_map('floatval', array_column($dailyData, 'qty'));
        $count = count($values);
        $level = $values[0];
        $trend = $count > 1 ? $values[1] - $values[0] : 0.0;

        for ($index = 1; $index < $count; $index++) {
            $previousLevel = $level;

            if ($beta === null) {
                $level = ($alpha * $values[$index]) + ((1 - $alpha) * $level);

                continue;
            }

            $level = ($alpha * $values[$index]) + ((1 - $alpha) * ($level + $trend));
            $trend = ($beta * ($level - $previousLevel)) + ((1 - $beta) * $trend);
        }

        $lastDate = CarbonImmutable::parse($dailyData[array_key_last($dailyData)]['date']);
        $predictions = [];

        for ($day = 1; $day <= $horizonDays; $day++) {
            $predictedValue = $beta === null
                ? $level
                : $level + ($day * $trend);

            $predictions[] = [
                'date' => $lastDate->addDays($day)->toDateString(),
                'predicted_qty' => round(max(0, $predictedValue), 2),
            ];
        }

        return $predictions;
    }
}
