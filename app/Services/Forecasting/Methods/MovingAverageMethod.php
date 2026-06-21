<?php

namespace App\Services\Forecasting\Methods;

use Carbon\CarbonImmutable;

class MovingAverageMethod
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

        $values = array_column($dailyData, 'qty');
        $window = min(count($values), 30);
        $recent = array_slice($values, -$window);
        $average = $recent === [] ? 0.0 : array_sum($recent) / count($recent);
        $lastDate = CarbonImmutable::parse($dailyData[array_key_last($dailyData)]['date']);

        $predictions = [];

        for ($day = 1; $day <= $horizonDays; $day++) {
            $predictions[] = [
                'date' => $lastDate->addDays($day)->toDateString(),
                'predicted_qty' => round(max(0, $average), 2),
            ];
        }

        return $predictions;
    }
}
