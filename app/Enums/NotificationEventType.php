<?php

namespace App\Enums;

enum NotificationEventType: string
{
    case RequisitionSubmitted = 'requisition_submitted';
    case RequisitionStatusChanged = 'requisition_status_changed';
    case BookingSubmitted = 'booking_submitted';
    case BookingStatusChanged = 'booking_status_changed';
    case LowStock = 'low_stock';
    case ProcurementRecommendation = 'procurement_recommendation';
    case HandoverVerification = 'handover_verification';

    public function label(): string
    {
        return match ($this) {
            self::RequisitionSubmitted => 'Requisition submitted',
            self::RequisitionStatusChanged => 'Requisition status changed',
            self::BookingSubmitted => 'Booking submitted',
            self::BookingStatusChanged => 'Booking status changed',
            self::LowStock => 'Low stock alert',
            self::ProcurementRecommendation => 'Procurement recommendation',
            self::HandoverVerification => 'Handover verification',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::RequisitionSubmitted => 'When a new requisition is submitted for review.',
            self::RequisitionStatusChanged => 'When your requisition is approved, rejected, or updated.',
            self::BookingSubmitted => 'When a new asset booking request is submitted.',
            self::BookingStatusChanged => 'When your booking is approved, rejected, or updated.',
            self::LowStock => 'When consumable stock drops below the reorder threshold.',
            self::ProcurementRecommendation => 'When a forecast predicts an upcoming stockout.',
            self::HandoverVerification => 'When an internal asset handover needs your verification.',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ],
            self::cases(),
        );
    }
}
