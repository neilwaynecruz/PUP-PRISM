<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\UpdateForecastProfileRequest;
use App\Models\ForecastProfile;
use App\Models\ForecastSnapshot;
use App\Models\Product;
use App\Services\DashboardStatsService;
use App\Services\Forecasting\DemandForecaster;
use App\Services\Forecasting\ForecastPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ForecastController extends Controller
{
    public function __construct(
        private readonly DemandForecaster $forecaster,
        private readonly ForecastPresenter $presenter,
        private readonly DashboardStatsService $dashboardStats,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasAnyRole(['Admin', 'Supply Head']), 403);

        $search = $request->string('search')->trim()->toString();
        $urgency = $request->string('urgency')->trim()->toString();
        $method = $request->string('method')->trim()->toString();
        $minConfidence = $request->filled('min_confidence')
            ? $request->float('min_confidence')
            : null;

        $products = Product::query()
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->with([
                'stock:id,product_id,on_hand_qty',
                'forecastProfile:id,product_id,method',
                'latestForecastSnapshot',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($method !== '', function ($query) use ($method) {
                $query->where(function ($nested) use ($method) {
                    $nested->whereHas('latestForecastSnapshot', fn ($snapshotQuery) => $snapshotQuery->where('forecast_method', $method))
                        ->orWhereHas('forecastProfile', fn ($profileQuery) => $profileQuery->where('method', $method));
                });
            })
            ->when($minConfidence !== null, function ($query) use ($minConfidence) {
                $query->whereHas('latestForecastSnapshot', fn ($snapshotQuery) => $snapshotQuery->where('confidence_score', '>=', $minConfidence));
            })
            ->when($urgency !== '', function ($query) use ($urgency) {
                $query->whereHas('latestForecastSnapshot', function ($snapshotQuery) use ($urgency) {
                    match ($urgency) {
                        'urgent' => $snapshotQuery->where('predicted_days_until_stockout', '<=', 7),
                        'at_risk' => $snapshotQuery->whereBetween('predicted_days_until_stockout', [8, 14]),
                        'stable' => $snapshotQuery->where('predicted_days_until_stockout', '>', 14),
                        'unknown' => $snapshotQuery->whereNull('predicted_days_until_stockout'),
                        default => null,
                    };
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $product) => $this->formatIndexRow($product));

        return Inertia::render('inventory/forecasting/Index', [
            'filters' => [
                'search' => $search,
                'urgency' => $urgency,
                'method' => $method,
                'min_confidence' => $minConfidence,
            ],
            'forecastSummary' => $this->dashboardStats->getForecastSummary(),
            'methodOptions' => $this->presenter->methodOptions(),
            'products' => $products,
        ]);
    }

    public function show(Request $request, Product $product): Response
    {
        abort_unless($request->user()?->hasAnyRole(['Admin', 'Supply Head']), 403);
        abort_unless($product->type === ProductType::Consumable, 404);

        $product->load([
            'stock:id,product_id,on_hand_qty',
            'forecastProfile',
        ]);

        $forecast = $this->resolveForecast($product);
        $profile = $this->resolveProfile($product);

        return Inertia::render('inventory/forecasting/Show', [
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'on_hand_qty' => $product->stock?->on_hand_qty ?? 0,
                'reorder_threshold' => $product->reorder_threshold,
            ],
            'forecast' => $forecast,
            'profile' => $profile,
            'confidenceExplanation' => $forecast !== null
                ? $this->presenter->confidenceExplanation($forecast)
                : __('No forecast is available for this product yet.'),
            'methodOptions' => $this->presenter->methodOptions(),
        ]);
    }

    public function updateProfile(UpdateForecastProfileRequest $request, Product $product): RedirectResponse
    {
        abort_unless($product->type === ProductType::Consumable, 404);

        ForecastProfile::query()->updateOrCreate(
            ['product_id' => $product->id],
            [
                ...$request->validated(),
                'is_active' => true,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Forecast profile updated.')]);

        return to_route('inventory.forecasting.show', $product);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatIndexRow(Product $product): array
    {
        $snapshot = $product->latestForecastSnapshot;

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'on_hand_qty' => $product->stock?->on_hand_qty ?? 0,
            'profile_method' => $product->forecastProfile?->method,
            'snapshot' => $snapshot instanceof ForecastSnapshot
                ? [
                    'forecast_method' => $snapshot->forecast_method,
                    'current_on_hand_qty' => $snapshot->current_on_hand_qty,
                    'predicted_daily_consumption' => round($snapshot->predicted_daily_consumption, 2),
                    'predicted_days_until_stockout' => $snapshot->predicted_days_until_stockout,
                    'predicted_stockout_date' => $this->presenter->formatDateString($snapshot->predicted_stockout_date),
                    'recommended_reorder_qty' => $snapshot->recommended_reorder_qty,
                    'confidence_score' => $snapshot->confidence_score !== null
                        ? round($snapshot->confidence_score, 2)
                        : null,
                    'forecast_date' => $this->presenter->formatDateString($snapshot->forecast_date),
                ]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveForecast(Product $product): ?array
    {
        $latestSnapshot = ForecastSnapshot::query()
            ->where('product_id', $product->id)
            ->latest('forecast_date')
            ->latest('generated_at')
            ->first();

        if (
            $latestSnapshot instanceof ForecastSnapshot
            && $this->presenter->isSnapshotFromToday($latestSnapshot)
        ) {
            return $this->presenter->fromSnapshot($latestSnapshot);
        }

        return $this->presenter->fromResult(
            $this->forecaster->forecast($product, $product->forecastProfile),
            'live',
        );
    }

    /**
     * @return array<string, int|string>
     */
    private function resolveProfile(Product $product): array
    {
        $profile = $product->forecastProfile;

        if ($profile instanceof ForecastProfile) {
            return [
                'method' => $profile->method,
                'lookback_days' => $profile->lookback_days,
                'forecast_horizon_days' => $profile->forecast_horizon_days,
                'lead_time_days' => $profile->lead_time_days,
                'safety_stock_days' => $profile->safety_stock_days,
            ];
        }

        return [
            'method' => 'exponential_smoothing',
            'lookback_days' => 90,
            'forecast_horizon_days' => 30,
            'lead_time_days' => 14,
            'safety_stock_days' => 7,
        ];
    }
}
