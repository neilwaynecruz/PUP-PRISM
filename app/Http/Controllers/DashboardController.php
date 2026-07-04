<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Models\User;
use App\Services\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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

        $validated = Validator::make($request->all(), [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ])->validate();

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
