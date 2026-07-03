<?php

namespace App\Enums;

enum StockMovementReasonCode: string
{
    case DataCorrection = 'data_correction';
    case FoundStock = 'found_stock';
    case Damaged = 'damaged';
    case Expired = 'expired';
    case ReturnToStock = 'return_to_stock';
    case Shrinkage = 'shrinkage';
    case RoutineCount = 'routine_count';
    case SpotCheck = 'spot_check';
    case VarianceReview = 'variance_review';

    /**
     * @return list<string>
     */
    public static function adjustmentValues(): array
    {
        return [
            self::DataCorrection->value,
            self::FoundStock->value,
            self::Damaged->value,
            self::Expired->value,
            self::ReturnToStock->value,
            self::Shrinkage->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function cycleCountValues(): array
    {
        return [
            self::RoutineCount->value,
            self::SpotCheck->value,
            self::VarianceReview->value,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::DataCorrection => 'Data correction',
            self::FoundStock => 'Found stock',
            self::Damaged => 'Damaged stock',
            self::Expired => 'Expired stock',
            self::ReturnToStock => 'Return to stock',
            self::Shrinkage => 'Shrinkage',
            self::RoutineCount => 'Routine count',
            self::SpotCheck => 'Spot check',
            self::VarianceReview => 'Variance review',
        };
    }
}
