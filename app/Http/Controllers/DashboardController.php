<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Models\User;
use App\Services\DashboardStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationValidator;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private DashboardStatsService $statsService) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $canViewForecasting = $user->hasAnyRole(['Admin', 'Supply Head']);
        $canViewProcurement = $user->hasAnyRole(['Admin', 'Supply Head']);

        $maxRangeDays = max(1, (int) config('dashboard.max_range_days', 366));
        $validator = Validator::make($request->all(), [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $validator->after(function (ValidationValidator $validator) use ($request, $maxRangeDays): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $fromInput = $request->input('from');
            $toInput = $request->input('to');

            if ($fromInput === null && $toInput === null) {
                return;
            }

            $from = is_string($fromInput) && $fromInput !== ''
                ? CarbonImmutable::parse($fromInput)->startOfDay()
                : CarbonImmutable::now()->startOfMonth();
            $to = is_string($toInput) && $toInput !== ''
                ? CarbonImmutable::parse($toInput)->endOfDay()
                : CarbonImmutable::now()->endOfDay();

            if ($from->diffInDays($to) > $maxRangeDays) {
                $validator->errors()->add('to', __('The dashboard date range cannot exceed :days days.', [
                    'days' => $maxRangeDays,
                ]));
            }
        });

        $validated = $validator->validate();

        $range = ['from' => $validated['from'] ?? null, 'to' => $validated['to'] ?? null];
        $emptyForecastSummary = [
            'forecast_date' => null,
            'last_generated_at' => null,
            'urgent_count' => 0,
            'at_risk_count' => 0,
            'average_confidence' => null,
            'items' => [],
        ];

        $dashboardVariant = match (true) {
            $user->hasRole('Admin') => 'admin',
            $user->hasRole('Supply Head') => 'supply_head',
            default => 'custodian',
        };

        $stats = match ($dashboardVariant) {
            'admin' => $this->statsService->getAdminStats($range),
            'supply_head' => $this->statsService->getProcurementStats($range),
            default => $this->statsService->getCustodianStats($user, $range),
        };

        return Inertia::render('Dashboard', [
            'dashboardVariant' => $dashboardVariant,
            'canViewForecasting' => $canViewForecasting,
            'dateRange' => $range,
            'alerts' => $stats['alerts'] ?? [],
            'forecastSummary' => $canViewForecasting
                ? ($stats['forecastSummary'] ?? $this->statsService->getForecastSummary())
                : $emptyForecastSummary,
            'lowStock' => $stats['lowStock'] ?? [],
            'unserviceableAssets' => $stats['unserviceableAssets'] ?? [],
            'kpiSummary' => $stats['kpiSummary'] ?? [],
            'nearExpiryLots' => $stats['nearExpiryLots'] ?? [],
            'expiredLots' => $stats['expiredLots'] ?? [],
            'custodianSummary' => $stats['custodianSummary'] ?? null,
            'assetStatusCounts' => [
                'labels' => [AssetStatus::Unserviceable->value, AssetStatus::Condemned->value],
                'data' => [
                    (int) ($stats['assetStatusCounts'][AssetStatus::Unserviceable->value] ?? 0),
                    (int) ($stats['assetStatusCounts'][AssetStatus::Condemned->value] ?? 0),
                ],
            ],
            'receivingTrends' => $stats['receivingTrends'] ?? ['labels' => [], 'data' => []],
            'issuingTrends' => $stats['issuingTrends'] ?? ['labels' => [], 'data' => []],
            'requisitionSummary' => $stats['requisitionSummary'] ?? [],
            'bookingSummary' => $stats['bookingSummary'] ?? [],
            'purchaseOrderSummary' => $stats['purchaseOrderSummary'] ?? [],
            'supplierPerformance' => $stats['supplierPerformance'] ?? [],
            'assetConditionSummary' => $stats['assetConditionSummary'] ?? [],
            'recentlyDeleted' => $stats['recentlyDeleted'] ?? [],
            'exportUrls' => $user->hasRole('Admin') ? [
                'assetConditionsCsv' => route('inventory.reports.asset-conditions', ['format' => 'csv'], absolute: false),
                'assetConditionsPdf' => route('inventory.reports.asset-conditions', ['format' => 'pdf'], absolute: false),
            ] : null,
        ]);
    }
}
