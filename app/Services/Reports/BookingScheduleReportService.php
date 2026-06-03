<?php

namespace App\Services\Reports;

use App\Models\Booking;
use App\Services\Reports\Support\TableReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class BookingScheduleReportService extends AbstractTableReportService
{
    public function build(Request $request): TableReport
    {
        $calendarWindowStart = CarbonImmutable::now()->subDays(30);
        $calendarWindowEnd = CarbonImmutable::now()->addMonths(6);

        $query = Booking::query()
            ->with([
                'asset:id,product_id,position_id,tag_code',
                'asset.product:id,name',
                'requester:id,name,email,position_id',
                'requesterPosition:id,department_id,title',
                'approver:id,name,email,position_id',
                'approverPosition:id,department_id,title',
                'requesterPosition.department:id,name',
                'approverPosition.department:id,name',
            ])
            ->where('end_at', '>=', $calendarWindowStart)
            ->where('start_at', '<=', $calendarWindowEnd)
            ->orderBy('start_at');

        $rows = (function () use ($query) {
            foreach ($query->lazy(200) as $booking) {
                yield [
                    'asset_tag' => $booking->asset?->tag_code ?? '',
                    'asset_name' => $booking->asset?->product?->name ?? '',
                    'status' => $booking->status->value,
                    'start_at' => optional($booking->start_at)?->format('Y-m-d H:i:s T') ?? '',
                    'end_at' => optional($booking->end_at)?->format('Y-m-d H:i:s T') ?? '',
                    'requester' => $booking->requester?->name ?? $booking->requester?->email ?? '',
                    'requester_position' => $this->formatPosition(
                        $booking->requesterPosition?->title,
                        $booking->requesterPosition?->department?->name,
                    ),
                    'approver' => $booking->approver?->name ?? $booking->approver?->email ?? '',
                    'purpose' => $booking->purpose ?? '',
                ];
            }
        })();

        return new TableReport(
            title: 'Booking Schedule Report',
            filenameBase: 'booking-schedule-report',
            filters: $this->normalizeFilters([
                'Schedule window' => sprintf(
                    '%s to %s',
                    $calendarWindowStart->format('Y-m-d'),
                    $calendarWindowEnd->format('Y-m-d'),
                ),
            ]),
            columns: [
                'asset_tag' => 'Asset tag',
                'asset_name' => 'Asset name',
                'status' => 'Status',
                'start_at' => 'Start',
                'end_at' => 'End',
                'requester' => 'Requester',
                'requester_position' => 'Requester position',
                'approver' => 'Approver',
                'purpose' => 'Purpose',
            ],
            rows: $rows,
            generatedBy: $this->generatedBy($request),
            generatedAt: CarbonImmutable::now(),
        );
    }
}
