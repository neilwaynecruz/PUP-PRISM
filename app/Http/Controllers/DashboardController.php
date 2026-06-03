<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
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

        $validated = Validator::make($request->all(), [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ])->validate();

        $range = ['from' => $validated['from'] ?? null, 'to' => $validated['to'] ?? null];

        $stats = $user->hasRole('Admin')
            ? $this->statsService->getAdminStats($range)
            : [];

        return Inertia::render('Dashboard', [
            'dateRange' => $range,
            'alerts' => $stats['alerts'] ?? [],
            'lowStock' => $stats['lowStock'] ?? [],
            'unserviceableAssets' => $stats['unserviceableAssets'] ?? [],
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
            'assetConditionSummary' => $stats['assetConditionSummary'] ?? [],
            'exportUrls' => $user->hasRole('Admin') ? [
                'assetConditionsCsv' => route('inventory.reports.asset-conditions', ['format' => 'csv'], absolute: false),
                'assetConditionsPdf' => route('inventory.reports.asset-conditions', ['format' => 'pdf'], absolute: false),
            ] : null,
        ]);
    }
}
