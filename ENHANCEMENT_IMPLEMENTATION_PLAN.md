# PUP PRISM — Enhancement Implementation Plan

> **Purpose:** Detailed, actionable implementation plans for 12 high-impact enhancements.
> **Status:** Proposed for Prioritization
> **Target:** Production-grade inventory system with predictive intelligence, real-time collaboration, and complete procurement lifecycle.

---

## Table of Contents

1. [Predictive Analytics & Demand Forecasting Engine](#1-predictive-analytics--demand-forecasting-engine)
2. [Real-Time WebSocket Upgrade (Laravel Reverb)](#2-real-time-websocket-upgrade-laravel-reverb)
3. [Supplier & Purchase Order Management](#3-supplier--purchase-order-management)
4. [Mobile PWA + Advanced Barcode/QR Ecosystem](#4-mobile-pwa--advanced-barcodeqr-ecosystem)
5. [Inventory Optimization Engine (ABC/EOQ/Safety Stock)](#5-inventory-optimization-engine-abceoqsafety-stock)
6. [Multi-Level Approval Workflow Engine](#6-multi-level-approval-workflow-engine)
7. [Asset Lifecycle & Maintenance Tracking](#7-asset-lifecycle--maintenance-tracking)
8. [Department Budget Tracking & Financial Integration](#8-department-budget-tracking--financial-integration)
9. [In-App Notification Center & Preferences](#9-in-app-notification-center--preferences)
10. [Advanced Security & Session Management](#10-advanced-security--session-management)
11. [Performance & Infrastructure Upgrades](#11-performance--infrastructure-upgrades)
12. [Bulk Import/Export & Data Migration Tools](#12-bulk-importexport--data-migration-tools)

---

## 1. Predictive Analytics & Demand Forecasting Engine

**Impact:** HIGH | **Effort:** 5-7 days | **Risk:** Low

### Business Value
Eliminates guesswork from reordering. Instead of reacting to low-stock alerts, the system predicts when stock will run out and recommends how much to order — reducing both stockouts and overstock.

### Architecture

```
┌─────────────────────────────────────────────────────────┐
│  app/Services/Forecasting/                              │
│  ├── DemandForecaster.php          ← Main orchestrator │
│  ├── Models/                                            │
│  │   ├── ForecastProfile.php       ← Config per product │
│  │   └── ForecastSnapshot.php      ← Cached predictions │
│  ├── Methods/                                           │
│  │   ├── MovingAverageMethod.php   ← Simple avg         │
│  │   ├── ExponentialSmoothing.php  ← Weighted recent    │
│  │   └── SeasonalMethod.php        ← Monthly patterns   │
│  ├── ConsumptionDataCollector.php  ← Queries movements  │
│  └── ReorderRecommender.php        ← Suggests POs       │
├── app/Console/Commands/GenerateDemandForecasts.php       │
├── app/Http/Controllers/Api/ForecastController.php       │
├── database/migrations/                                  │
│   ├── xxxx_create_forecast_profiles_table.php           │
│   └── xxxx_create_forecast_snapshots_table.php          │
└── resources/js/pages/inventory/forecasting/             │
    ├── Index.vue                    ← Dashboard          │
    ├── Show.vue                     ← Per-product detail │
    └── Components/                                        │
        ├── ForecastChart.vue        ← Prediction viz      │
        └── ReorderRecommendation.vue ← Actionable card    │
┌─────────────────────────────────────────────────────────┐
```

### Step-by-Step Implementation

#### Step 1: Create Forecast Model & Migration

**`database/migrations/xxxx_create_forecast_profiles_table.php`**
```php
Schema::create('forecast_profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->string('method')->default('moving_average'); // moving_average|exponential_smoothing|seasonal
    $table->integer('lookback_days')->default(90);
    $table->integer('forecast_horizon_days')->default(30);
    $table->decimal('smoothing_factor', 4, 2)->nullable(); // alpha for exponential
    $table->decimal('trend_factor', 4, 2)->nullable();     // beta
    $table->decimal('seasonal_factor', 4, 2)->nullable();  // gamma
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**`database/migrations/xxxx_create_forecast_snapshots_table.php`**
```php
Schema::create('forecast_snapshots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->date('forecast_date');
    $table->decimal('predicted_daily_consumption', 10, 2);
    $table->integer('predicted_days_until_stockout');
    $table->integer('recommended_reorder_qty');
    $table->decimal('confidence_score', 5, 2)->nullable(); // 0-100
    $table->json('raw_data')->nullable();   // daily breakdown
    $table->date('generated_at');
    $table->timestamps();

    $table->index(['product_id', 'forecast_date']);
});
```

#### Step 2: Build Consumption Data Collector

**`app/Services/Forecasting/ConsumptionDataCollector.php`**

This queries your existing `StockMovement` records to build a time-series of daily consumption:

```php
class ConsumptionDataCollector
{
    /**
     * Get daily consumption for a product over a period.
     * @return array<array{date: string, qty: int}>
     */
    public function getDailyConsumption(Product $product, int $lookbackDays = 90): array
    {
        $since = CarbonImmutable::now()->subDays($lookbackDays);

        // Pull all issue movements for this product, grouped by day
        $movements = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('movement_type', 'issue')
            ->where('performed_at', '>=', $since)
            ->select(
                DB::raw('DATE(performed_at) as date'),
                DB::raw('CAST(SUM(ABS(qty_delta)) AS INTEGER) as total_issued')
            )
            ->groupBy(DB::raw('DATE(performed_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Fill zeroes for days with no movement
        $daily = [];
        $cursor = $since->copy();
        $today = CarbonImmutable::now();
        while ($cursor <= $today) {
            $dateKey = $cursor->toDateString();
            $daily[] = [
                'date' => $dateKey,
                'qty'  => (int) ($movements[$dateKey]->total_issued ?? 0),
            ];
            $cursor = $cursor->addDay();
        }

        return $daily;
    }

    /**
     * Get consumption grouped by weekday/month for seasonal analysis
     */
    public function getMonthlyAggregates(Product $product): Collection
    {
        return StockMovement::query()
            ->where('product_id', $product->id)
            ->where('movement_type', 'issue')
            ->select(
                DB::raw("TO_CHAR(performed_at, 'YYYY-MM') as month"),
                DB::raw('CAST(SUM(ABS(qty_delta)) AS INTEGER) as total')
            )
            ->groupBy(DB::raw("TO_CHAR(performed_at, 'YYYY-MM')"))
            ->orderBy('month')
            ->get();a
    }
}
```

#### Step 3: Build Forecasting Methods

**`app/Services/Forecasting/Methods/MovingAverageMethod.php`**

```php
class MovingAverageMethod
{
    /**
     * Simple moving average of the last N days of consumption.
     */
    public function forecast(array $dailyData, int $horizonDays = 30): array
    {
        $values = array_column($dailyData, 'qty');
        $window = min(count($values), 30); // 30-day moving average
        $recent = array_slice($values, -$window);
        $avg = count($recent) > 0 ? array_sum($recent) / count($recent) : 0;

        $predictions = [];
        $lastDate = CarbonImmutable::parse(end($dailyData)['date'] ?? now());

        for ($i = 1; $i <= $horizonDays; $i++) {
            $predictions[] = [
                'date' => $lastDate->addDay()->toDateString(),
                'predicted_qty' => round($avg, 2),
            ];
        }

        return $predictions;
    }
}
```

**`app/Services/Forecasting/Methods/ExponentialSmoothing.php`**

```php
class ExponentialSmoothing
{
    /**
     * Holt-Winters exponential smoothing.
     * alpha: level smoothing (0-1), higher = more weight to recent
     */
    public function forecast(
        array $dailyData,
        int $horizonDays = 30,
        float $alpha = 0.3,
        ?float $beta = null,  // trend smoothing
    ): array {
        $values = array_column($dailyData, 'qty');
        $n = count($values);
        if ($n === 0) return [];

        // Initialize
        $level = $values[0];
        $trend = $beta !== null ? ($values[1] - $values[0] ?? 0) : 0;

        // Smooth through history
        for ($i = 1; $i < $n; $i++) {
            $priorLevel = $level;
            $level = $alpha * $values[$i] + (1 - $alpha) * ($level + $trend);
            if ($beta !== null) {
                $trend = $beta * ($level - $priorLevel) + (1 - $beta) * $trend;
            }
        }

        // Forecast forward
        $predictions = [];
        $lastDate = CarbonImmutable::parse(end($dailyData)['date']);
        for ($i = 1; $i <= $horizonDays; $i++) {
            $predicted = $level + $i * $trend;
            $predictions[] = [
                'date' => $lastDate->addDay()->toDateString(),
                'predicted_qty' => round(max(0, $predicted), 2),
            ];
        }

        return $predictions;
    }
}
```

#### Step 4: Build Main Demand Forecaster

**`app/Services/Forecasting/DemandForecaster.php`**

```php
class DemandForecaster
{
    public function __construct(
        private readonly ConsumptionDataCollector $collector,
        private readonly MovingAverageMethod $movingAverage,
        private readonly ExponentialSmoothing $exponentialSmoothing,
    ) {}

    /**
     * Generate forecast for a single product.
     */
    public function forecast(Product $product, ?ForecastProfile $profile = null): ForecastResult
    {
        $profile ??= $product->forecastProfile ?? new ForecastProfile([
            'method' => 'moving_average',
            'lookback_days' => 90,
            'forecast_horizon_days' => 30,
        ]);

        $dailyData = $this->collector->getDailyConsumption(
            $product,
            $profile->lookback_days
        );

        $predictions = match ($profile->method) {
            'exponential_smoothing' => $this->exponentialSmoothing->forecast(
                $dailyData,
                $profile->forecast_horizon_days,
                $profile->smoothing_factor ?? 0.3,
                $profile->trend_factor,
            ),
            default => $this->movingAverage->forecast(
                $dailyData,
                $profile->forecast_horizon_days,
            ),
        };

        // Calculate stockout prediction
        $avgDailyConsumption = count($predictions) > 0
            ? array_sum(array_column($predictions, 'predicted_qty')) / count($predictions)
            : 0;

        $onHand = $product->stock?->on_hand_qty ?? 0;
        $daysUntilStockout = $avgDailyConsumption > 0
            ? (int) floor($onHand / $avgDailyConsumption)
            : PHP_INT_MAX;

        // Recommended reorder: enough for lead time + safety stock
        $leadTimeDays = $product->lead_time_days ?? 7;
        $safetyStock = (int) ceil($avgDailyConsumption * 3); // 3 days safety
        $recommendedQty = max(
            0,
            (int) ceil($avgDailyConsumption * ($leadTimeDays + 7)) - $onHand + $safetyStock
        );

        return new ForecastResult(
            productId: $product->id,
            predictions: $predictions,
            avgDailyConsumption: round($avgDailyConsumption, 2),
            daysUntilStockout: $daysUntilStockout,
            recommendedReorderQty: $recommendedQty,
            generatedAt: CarbonImmutable::now(),
        );
    }

    /**
     * Batch forecast all active consumable products.
     */
    public function forecastAll(): array
    {
        $products = Product::query()
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->with('stock', 'forecastProfile')
            ->get();

        $results = [];
        foreach ($products as $product) {
            try {
                $results[$product->id] = $this->forecast($product);
            } catch (\Exception $e) {
                Log::warning("Forecast failed for product {$product->id}: {$e->getMessage()}");
            }
        }

        return $results;
    }
}
```

#### Step 5: Scheduled Command + Dashboard Integration

**`app/Console/Commands/GenerateDemandForecasts.php`**

```php
#[Signature('app:generate-demand-forecasts')]
#[Description('Generate demand forecasts for active consumable products')]
class GenerateDemandForecasts extends Command
{
    public function handle(DemandForecaster $forecaster): void
    {
        $this->info('Generating demand forecasts...');
        $results = $forecaster->forecastAll();

        // Persist to forecast_snapshots
        $now = CarbonImmutable::now();
        foreach ($results as $productId => $result) {
            ForecastSnapshot::updateOrCreate(
                [
                    'product_id' => $productId,
                    'forecast_date' => $now->toDateString(),
                ],
                [
                    'predicted_daily_consumption' => $result->avgDailyConsumption,
                    'predicted_days_until_stockout' => $result->daysUntilStockout,
                    'recommended_reorder_qty' => $result->recommendedReorderQty,
                    'confidence_score' => $result->confidenceScore,
                    'raw_data' => json_encode($result->predictions),
                    'generated_at' => $now,
                ]
            );
        }

        // Also generate reorder alerts for items approaching stockout
        $this->generateReorderAlerts($results);

        $this->info(count($results) . ' forecasts generated.');
    }

    private function generateReorderAlerts(array $results): void
    {
        foreach ($results as $productId => $result) {
            if ($result->daysUntilStockout <= 7 && $result->daysUntilStockout > 0) {
                // Create inventory alert for near-stockout
                InventoryAlert::create([
                    'type' => 'forecast_stockout',
                    'product_id' => $productId,
                    'message' => "Predicted stockout in {$result->daysUntilStockout} days. Recommended reorder: {$result->recommendedReorderQty} units.",
                    'detected_at' => CarbonImmutable::now(),
                ]);
            }
        }
    }
}
```

Register in `routes/console.php`:
```php
Schedule::command('app:generate-demand-forecasts')->dailyAt('02:00');
```

#### Step 6: Frontend — Forecasting Dashboard Widget

Add to `DashboardStatsService`:
```php
public function forecastSummary(): array
{
    return ForecastSnapshot::query()
        ->whereDate('forecast_date', CarbonImmutable::now()->toDateString())
        ->where('predicted_days_until_stockout', '<=', 14)
        ->with('product:id,name,sku')
        ->orderBy('predicted_days_until_stockout')
        ->limit(10)
        ->get()
        ->map(fn ($s) => [
            'product_name' => $s->product?->name,
            'sku' => $s->product?->sku,
            'days_until_stockout' => $s->predicted_days_until_stockout,
            'recommended_reorder_qty' => $s->recommended_reorder_qty,
            'predicted_daily_consumption' => $s->predicted_daily_consumption,
        ])
        ->toArray();
}
```

New Vue component — **`resources/js/components/inventory/ForecastWidget.vue`**:
Shows a compact table of "Products predicted to stock out in 14 days" with recommended reorder qty. Links to product detail page.

#### Step 7: Product Detail — Forecast Tab

Add to `ProductController@show`:
```php
$forecast = ForecastSnapshot::query()
    ->where('product_id', $product->id)
    ->whereDate('forecast_date', CarbonImmutable::now()->toDateString())
    ->first();

return Inertia::render('inventory/products/Show', [
    'product' => ProductResource::make($product),
    'forecast' => $forecast ? [
        'predicted_daily_consumption' => $forecast->predicted_daily_consumption,
        'days_until_stockout' => $forecast->predicted_days_until_stockout,
        'recommended_reorder_qty' => $forecast->recommended_reorder_qty,
        'daily_breakdown' => json_decode($forecast->raw_data, true),
    ] : null,
]);
```

---

## 2. Real-Time WebSocket Upgrade (Laravel Reverb)

**Impact:** HIGH | **Effort:** 4-6 days | **Risk:** Low

### ⚠️ Pre-verification Notes
- `config/broadcasting.php` — **DOES NOT EXIST**, must be published via `php artisan config:publish broadcasting`
- `routes/channels.php` — **DOES NOT EXIST**, must be created
- `app/Events/` directory — **DOES NOT EXIST**, all events are new files
- No broadcasting driver is configured (`.env` has `BROADCAST_CONNECTION=log`)
- No `laravel-echo` or `pusher-js` in `package.json` — must be installed with npm
- No Echo import in `resources/js/app.ts`
- All existing notifications use `['mail']` only in `via()` — must add `'database'` channel
- `notifications` table migration does **NOT** exist — must run `php artisan notifications:table`

### Business Value
Upgrades from 30-second polling to instant push updates. Users see approvals, new requisitions, and status changes without refreshing. Enables live notification badges and collaborative awareness.

### Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  Backend: Laravel Reverb (WebSocket Server)                 │
│  ├── config/broadcasting.php    ← MUST PUBLISH (missing)    │
│  ├── config/reverb.php          ← auto-generated            │
│  └── app/Events/                ← NEW directory             │
│      ├── RequisitionStatusChanged.php                       │
│      ├── BookingStatusChanged.php                           │
│      ├── StockLevelChanged.php                              │
│      └── HandoverInitiated.php                              │
│                                                              │
│  Frontend: Laravel Echo + Pusher.js                          │
│  ├── resources/js/echo.ts       ← NEW file                  │
│  └── composables/useRealtime.ts ← NEW composable            │
│                                                              │
│  Prerequisites:                                              │
│  ├── php artisan config:publish broadcasting                │
│  ├── npm install laravel-echo pusher-js                     │
│  ├── php artisan notifications:table && migrate             │
│  └── Update all 6 notification via() to add 'database'      │
└─────────────────────────────────────────────────────────────┘
```

### Step-by-Step Implementation

#### Step 1: Publish Broadcasting Config (missing file)

```bash
php artisan config:publish broadcasting
```

This creates `config/broadcasting.php`. Then configure Reverb as the driver:
```php
'default' => env('BROADCAST_CONNECTION', 'reverb'),
```

#### Step 2: Install Reverb & Frontend Dependencies

```bash
composer require laravel/reverb
php artisan install:reverb --no-interaction
# Installs pusher/pusher-php-server automatically as a dependency

npm install laravel-echo pusher-js
```

The `install:reverb` command generates `config/reverb.php` with app keys automatically.

#### Step 3: Create Notifications Table Migration

```bash
php artisan notifications:table
php artisan migrate
```

This creates the `notifications` table that Laravel's `database` notification channel requires.

#### Step 2: Create Broadcast Events

**`app/Events/RequisitionStatusChanged.php`**

```php
class RequisitionStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Requisition $requisition,
        public string $action, // approved|rejected|issued|submitted
        public ?int $notifyUserId = null, // null = broadcast to role channel
    ) {}

    public function broadcastOn(): array
    {
        if ($this->notifyUserId) {
            return [
                new PrivateChannel("user.{$this->notifyUserId}"),
            ];
        }

        return [
            new PrivateChannel('role.supply-head'),
            new PrivateChannel('role.admin'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->requisition->id,
            'status' => $this->requisition->status->value,
            'action' => $this->action,
            'requester_name' => $this->requisition->requester?->name,
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'requisition.status.changed';
    }
}
```

**`app/Events/StockLevelChanged.php`** — triggered after issuance or receiving:

```php
class StockLevelChanged implements ShouldBroadcast
{
    public function __construct(
        public Product $product,
        public int $newOnHand,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('role.supply-head'),
            new PrivateChannel('role.admin'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->product->id,
            'sku' => $this->product->sku,
            'name' => $this->product->name,
            'new_on_hand' => $this->newOnHand,
        ];
    }
}
```

#### Step 3: Fire Events from Controllers

In **`RequisitionController@approve`**, after status update:
```php
event(new RequisitionStatusChanged($requisition, 'approved', $requisition->requester_id));
event(new RequisitionStatusChanged($requisition, 'approved')); // role channel
```

In **`InventoryService@issueRequisition`**, after stock deduction:
```php
event(new StockLevelChanged($product, $freshStock->on_hand_qty));
```

#### Step 4: Configure Broadcasting in Laravel

**`config/broadcasting.php`**:
```php
'connections' => [
    'reverb' => [
        'driver' => 'reverb',
        'key' => env('REVERB_APP_KEY'),
        'secret' => env('REVERB_APP_SECRET'),
        'app_id' => env('REVERB_APP_ID'),
        'options' => [
            'host' => env('REVERB_HOST', 'localhost'),
            'port' => env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'http'),
            'useTls' => env('REVERB_SCHEME', 'https') === 'https',
        ],
        'client_options' => [],
    ],
],
```

#### Step 5: Frontend — Echo Setup

**`resources/js/echo.ts`**:
```typescript
import Echo from 'laravel-echo';
import { usePage } from '@inertiajs/vue3';

let echo: Echo | null = null;

export function getEcho(): Echo {
    if (echo) return echo;

    const page = usePage();
    const authToken = (page.props.auth as { token?: string })?.token;

    echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
        auth: {
            headers: authToken ? { Authorization: `Bearer ${authToken}` } : {},
        },
    });

    return echo;
}

export function destroyEcho(): void {
    echo?.disconnect();
    echo = null;
}
```

#### Step 6: Replace Polling with WebSocket in Composables

**`resources/js/composables/useRealtime.ts`** — new composable that wraps Echo:

```typescript
import { onMounted, onUnmounted, ref } from 'vue';
import { getEcho, destroyEcho } from '@/echo';

interface ChannelSubscription {
    channel: string;
    event: string;
    handler: (data: unknown) => void;
}

export function useRealtime(subscriptions: ChannelSubscription[]) {
    const isConnected = ref(false);
    const lastEvent = ref<{ channel: string; event: string; data: unknown } | null>(null);
    let echo: ReturnType<typeof getEcho> | null = null;

    onMounted(() => {
        try {
            echo = getEcho();
            isConnected.value = true;

            for (const sub of subscriptions) {
                if (sub.channel.startsWith('private.')) {
                    echo.private(sub.channel).listen(sub.event, (data: unknown) => {
                        lastEvent.value = { channel: sub.channel, event: sub.event, data };
                        sub.handler(data);
                    });
                } else if (sub.channel.startsWith('presence.')) {
                    echo.join(sub.channel).listen(sub.event, (data: unknown) => {
                        lastEvent.value = { channel: sub.channel, event: sub.event, data };
                        sub.handler(data);
                    });
                } else {
                    echo.channel(sub.channel).listen(sub.event, (data: unknown) => {
                        lastEvent.value = { channel: sub.channel, event: sub.event, data };
                        sub.handler(data);
                    });
                }
            }
        } catch (e) {
            console.warn('WebSocket connection failed, falling back to polling', e);
            isConnected.value = false;
        }
    });

    onUnmounted(() => {
        destroyEcho();
    });

    return { isConnected, lastEvent };
}
```

#### Step 7: Channel Authorization Route

In **`routes/channels.php`** (create this file):
```php
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('role.{roleName}', function (User $user, string $roleName) {
    return $user->hasRole($roleName);
});

Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});
```

#### Step 8: Add 'database' Channel to Existing Notifications

All 6 existing notifications already have `toArray()` methods but only return `['mail']` from `via()`. Modify each:

```php
public function via(object $notifiable): array
{
    return ['mail', 'database'];
}
```

Also update to use `NotificationSent` event or a custom trait to avoid double-sending when both channels are enabled.

**Files to modify (6 files):**
- `app/Notifications/RequisitionSubmittedNotification.php`
- `app/Notifications/RequisitionStatusChangedNotification.php`
- `app/Notifications/BookingSubmittedNotification.php`
- `app/Notifications/BookingStatusChangedNotification.php`
- `app/Notifications/LowStockAlertNotification.php`
- `app/Notifications/HandoverVerificationNotification.php`

#### Step 9: Live Notification Badge

In `AppSidebarLayout.vue`, subscribe to role channel and update a reactive notification count. The notification bell should be added to the sidebar header area:

```typescript
const unreadCount = ref(0);

useRealtime([
    {
        channel: 'private.role.admin',
        event: 'requisition.status.changed',
        handler: (data: any) => {
            unreadCount.value++;
            // Show toast
            sonner.info(`Requisition #${data.id} ${data.action}`);
        },
    },
    {
        channel: 'private.role.supply-head',
        event: 'requisition.status.changed',
        handler: (data: any) => {
            unreadCount.value++;
        },
    },
]);
```

---

## 3. Supplier & Purchase Order Management

**Impact:** HIGH | **Effort:** 6-8 days | **Risk:** Low

### Business Value
Completes the procurement loop. Currently, the system detects low stock but doesn't help users act on it. With suppliers and POs, users can create orders directly from alerts, track delivery status, and evaluate supplier performance.

### Data Model

```
suppliers
├── id, name, contact_person, email, phone, address
├── website, payment_terms, lead_time_days
├── is_active, notes
└── timestamps

purchase_orders
├── id, supplier_id, po_number (auto-generated)
├── status (draft|sent|partial|received|cancelled)
├── subtotal, tax, total_amount (computed from lines)
├── requested_by, approved_by
├── expected_delivery_at, received_at
├── notes, timestamps, softDeletes

purchase_order_lines
├── id, purchase_order_id
├── product_id, qty_ordered
├── qty_received (incremental)
├── unit_price (decimal)
└── subtotal (computed)
```

### Step-by-Step Implementation

#### Step 1-4: Create Models, Migration, Relationship, Seeder

```bash
php artisan make:model Supplier -mf
php artisan make:model PurchaseOrder -mf
php artisan make:model PurchaseOrderLine -mf
```

**Supplier relationship on Product** — add `supplier_id` to products table:
```php
$table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
$table->integer('lead_time_days')->nullable()->after('reorder_threshold');
$table->decimal('unit_price', 10, 2)->nullable()->after('lead_time_days');
```

#### Step 5: Create Controllers & Policies

```php
class SupplierController extends Controller
{
    // index, create, store, show, edit, update, destroy
    // index: paginated list with search by name/contact
    // store: validate + create
    // update: validate + update
}

class PurchaseOrderController extends Controller
{
    // index: filtered by status, supplier, date range
    // create: load suppliers + products
    // store: create PO with lines
    // show: PO detail with line items + receiving progress
    // send: change status to 'sent' (triggers email to supplier)
    // receive: partial/full receive against PO lines
    // cancel: change status to 'cancelled'
}
```

#### Step 6: Auto-Generate POs from Low-Stock + Forecast

**`app/Services/Procurement/RequisitionToPoConverter.php`**:

```php
class PurchaseOrderGenerator
{
    /**
     * Generate a PO from low-stock products that have a preferred supplier.
     */
    public function generateFromAlerts(): ?PurchaseOrder
    {
        $products = Product::query()
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->whereNotNull('supplier_id')
            ->whereHas('stock', fn ($q) =>
                $q->whereColumn('on_hand_qty', '<=', 'products.reorder_threshold')
            )
            ->with('stock', 'supplier')
            ->get()
            ->groupBy('supplier_id');

        foreach ($products as $supplierId => $items) {
            $this->createPoForSupplier($supplierId, $items);
        }
    }

    private function createPoForSupplier(int $supplierId, Collection $products): PurchaseOrder
    {
        $lines = [];
        $totalAmount = 0;

        foreach ($products as $product) {
            $reorderQty = max(
                0,
                ($product->reorder_threshold * 2) - ($product->stock?->on_hand_qty ?? 0)
            );

            if ($reorderQty <= 0) continue;

            $lines[] = new PurchaseOrderLine([
                'product_id' => $product->id,
                'qty_ordered' => $reorderQty,
                'unit_price' => $product->unit_price ?? 0,
            ]);
            $totalAmount += ($product->unit_price ?? 0) * $reorderQty;
        }

        if (empty($lines)) return null;

        $po = PurchaseOrder::create([
            'supplier_id' => $supplierId,
            'po_number' => 'PO-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
            'status' => 'draft',
            'total_amount' => $totalAmount,
        ]);
        $po->lines()->saveMany($lines);

        return $po;
    }
}
```

#### Step 7: Receiving Against POs

Extend `ReceivingController@store` — add optional `purchase_order_id` and `purchase_order_line_id` to payload:

```php
DB::transaction(function () use ($poLine, $qty) {
    // Existing receive logic...
    $poLine->increment('qty_received', $qty);
    if ($poLine->qty_received >= $poLine->qty_ordered) {
        $poLine->purchaseOrder->updateStatus(); // checks all lines
    }
});
```

#### Step 8: Supplier Performance Dashboard

Add to `DashboardStatsService`:
```php
public function supplierPerformance(): array
{
    return Supplier::withCount(['purchaseOrders as total_pos'])
        ->withAvg(['purchaseOrders as avg_lead_time_days' => function ($q) {
            $q->whereNotNull('received_at')
              ->select(DB::raw('EXTRACT(DAY FROM (received_at - created_at))'));
        }])
        ->orderByDesc('total_pos')
        ->limit(10)
        ->get()
        ->toArray();
}
```

#### Step 9: Frontend Pages

New pages:
- **`resources/js/pages/inventory/suppliers/Index.vue`** — table with search, supplier list
- **`resources/js/pages/inventory/suppliers/Create.vue`** — form
- **`resources/js/pages/inventory/suppliers/Show.vue`** — detail with linked POs
- **`resources/js/pages/inventory/purchase-orders/Index.vue`** — filtered list
- **`resources/js/pages/inventory/purchase-orders/Create.vue`** — multi-line form with product search
- **`resources/js/pages/inventory/purchase-orders/Show.vue`** — detail with receiving progress bars

---

## 4. Mobile PWA + Advanced Barcode/QR Ecosystem

**Impact:** HIGH | **Effort:** 5-7 days | **Risk:** Medium

### Business Value
Enables field staff to receive stock, perform inventory counts, and audit assets using their phone camera — no specialized hardware needed. Directly improves accuracy and speed of physical inventory processes.

### Architecture

```
┌─────────────────────────────────────────────────────────┐
│  PWA Setup                                              │
│  ├── vite.config.ts            ← ADD VitePWA plugin     │
│  ├── public/manifest.json      ← auto-generated         │
│  └── public/sw.js              ← auto-generated         │
│                                                           │
│  Barcode/QR Module                                        │
│  ├── ✓ useQrScanner.ts         ← ALREADY EXISTS (jsQR)  │
│  │     ENHANCE: continuous scan, sound feedback          │
│  ├── ✓ QrScannerDialog.vue     ← ALREADY EXISTS         │
│  │     ENHANCE: batch scan mode, auto-navigate          │
│  ├── app/Services/Barcode/BarcodeGeneratorService.php    │
│  └── app/Services/Barcode/ProductLabelService.php        │
│                                                           │
│  Inventory Count Module (NEW)                             │
│  ├── app/Models/InventoryCount.php                        │
│  ├── app/Models/CountLine.php                             │
│  ├── app/Http/Controllers/Inventory/CountController.php   │
│  └── resources/js/pages/inventory/counts/                 │
│       ├── Index.vue                                       │
│       ├── Create.vue          ← Uses enhanced scanner     │
│       └── Show.vue            ← Variance report           │
└─────────────────────────────────────────────────────────┘
```

### ⚠️ Pre-verification Notes
- `resources/js/composables/useQrScanner.ts` — **ALREADY EXISTS** with full `jsQR` integration. Do NOT create a new scanner composable. Enhance the existing one.
- `resources/js/components/inventory/QrScannerDialog.vue` — **ALREADY EXISTS**. Do NOT create a new dialog. Enhance the existing one with batch/continuous scanning.
- No `public/manifest.json` — will be auto-generated by VitePWA plugin
- `vite-plugin-pwa` compatibility with Vite 8 must be verified before install

### Step-by-Step Implementation

#### Step 1: PWA Setup

```bash
npm install vite-plugin-pwa --save-dev
```

> ⚠️ **Vite 8 compatibility check:** The project uses Vite ^8.0.0. Verify `vite-plugin-pwa` supports this version. If incompatible, use `@serwist/vite` or `vite-plugin-pwa@beta`.

**`vite.config.ts`** addition:
```typescript
import { VitePWA } from 'vite-plugin-pwa';

plugins: [
    // ... existing plugins
    VitePWA({
        registerType: 'autoUpdate',
        includeAssets: ['favicon.ico'],
        manifest: {
            name: 'PUP PRISM',
            short_name: 'PRISM',
            description: 'Property & Resource Inventory System',
            theme_color: '#1e293b',
            background_color: '#ffffff',
            display: 'standalone',
        },
    }),
],
```

#### Step 2: Enhance Existing QR Scanner (not create new)

The existing `useQrScanner.ts` at `resources/js/composables/useQrScanner.ts:8` already handles:
- Camera access with environment-facing preference
- Frame-by-frame QR detection via `jsQR`
- Auto-stop on detection
- Error handling (permission denied, camera unavailable)

**Enhancements to add:**
- **Continuous scan mode** — don't auto-stop after first detection, emit events instead
- **Audio feedback** — short beep on successful scan via `AudioContext`
- **Barcode format support** — recognize Code128, EAN-13 in addition to QR (requires extending `jsQR` or adding a lightweight barcode decoder)
- **Torch/flashlight toggle** for low-light environments

#### Step 3: Barcode Generator Service (PHP)

**`app/Services/Barcode/BarcodeGeneratorService.php`**

Generate barcode images using a simple barcode library or QR codes via existing `jsqr`:

```php
class BarcodeGeneratorService
{
    /**
     * Generate QR code PNG for an asset tag.
     * Contains: product SKU, asset tag_code, and verification URL
     */
    public function generateAssetQrCode(Asset $asset): string
    {
        $data = json_encode([
            'type' => 'asset',
            'sku' => $asset->product->sku,
            'tag' => $asset->tag_code,
            'url' => route('api.assets.show', $asset->id),
        ]);

        // Use a QR code library (e.g., simplq or endroid/qr-code)
        $qrCode = QrCode::create($data)
            ->setSize(300)
            ->setMargin(10);

        // Return as base64 data URI or save to storage
        return $qrCode->writeDataUri();
    }

    /**
     * Generate product label (barcode + human-readable).
     * Returns HTML content for printable label sheet.
     */
    public function generateProductLabelSheet(Product $product): string
    {
        // Generate QR for product SKU
        $qrDataUri = $this->generateProductQrCode($product);

        return view('labels.product-label', [
            'product' => $product,
            'qrCode' => $qrDataUri,
            'generated_at' => CarbonImmutable::now(),
        ]);
    }
}
```

#### Step 4: Inventory Counting Module

**`database/migrations/xxxx_create_inventory_counts_table.php`**:
```php
Schema::create('inventory_counts', function (Blueprint $table) {
    $table->id();
    $table->string('status'); // in_progress|completed|approved
    $table->foreignId('counted_by')->constrained('users');
    $table->timestamp('counted_at');
    $table->text('notes')->nullable();
    $table->timestamps();
});

Schema::create('count_lines', function (Blueprint $table) {
    $table->id();
    $table->foreignId('inventory_count_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained();
    $table->foreignId('stock_lot_id')->nullable()->constrained();
    $table->integer('system_qty');
    $table->integer('physical_qty');
    $table->integer('variance'); // physical - system
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

**`app/Http/Controllers/Inventory/CountController.php`**:
```php
class CountController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|exists:products,id',
            'lines.*.physical_qty' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            $count = InventoryCount::create([
                'counted_by' => auth()->id(),
                'counted_at' => CarbonImmutable::now(),
                'status' => 'completed',
            ]);

            $lines = [];
            foreach ($validated['lines'] as $line) {
                $product = Product::with('stock')->findOrFail($line['product_id']);
                $systemQty = $product->stock?->on_hand_qty ?? 0;

                $lines[] = new CountLine([
                    'product_id' => $product->id,
                    'system_qty' => $systemQty,
                    'physical_qty' => $line['physical_qty'],
                    'variance' => $line['physical_qty'] - $systemQty,
                ]);

                // If variance > 0, auto-create adjustment movement
                // If variance != 0, flag for review
            }

            $count->lines()->saveMany($lines);
        });

        return redirect()->route('inventory.counts.index')
            ->with('success', 'Inventory count recorded.');
    }
}
```

#### Step 5: QR Scanner Dialog (upgrade existing)

Update **`resources/js/components/inventory/QrScannerDialog.vue`** — enhance with:
- Continuous scan mode (scan multiple items without closing)
- Sound feedback on successful scan
- Auto-navigation: scanning an asset tag code → navigates to asset detail
- Batch mode for receiving

---

## 5. Inventory Optimization Engine (ABC/EOQ/Safety Stock)

**Impact:** MEDIUM | **Effort:** 4-5 days | **Risk:** Low

### Business Value
Professional inventory management methodologies (ABC analysis, EOQ, safety stock) automate what most organizations do in spreadsheets. This helps prioritize attention on high-value items, optimize order quantities, and prevent stockouts scientifically.

### Implementation

#### ABC Classification Service

**`app/Services/Optimization/AbcAnalyzer.php`**

```php
class AbcAnalyzer
{
    /**
     * Run ABC analysis on all consumable products.
     * A = top 70% consumption value
     * B = next 20%
     * C = bottom 10%
     */
    public function analyze(): array
    {
        // Calculate annual consumption value (qty_issued * avg_price)
        $products = Product::query()
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->with('stock')
            ->get()
            ->map(function ($product) {
                $annualQty = StockMovement::query()
                    ->where('product_id', $product->id)
                    ->where('movement_type', 'issue')
                    ->where('performed_at', '>=', CarbonImmutable::now()->subYear())
                    ->sum(DB::raw('ABS(qty_delta)'));

                $avgPrice = $product->unit_price ?? 0;
                $annualValue = $annualQty * $avgPrice;

                return [
                    'product' => $product,
                    'annual_qty' => $annualQty,
                    'annual_value' => $annualValue,
                ];
            })
            ->sortByDesc('annual_value')
            ->values();

        $totalValue = $products->sum('annual_value');
        $cumulative = 0;

        return $products->map(function ($item) use ($totalValue, &$cumulative) {
            $cumulative += $totalValue > 0 ? ($item['annual_value'] / $totalValue) * 100 : 0;

            $classification = match (true) {
                $cumulative <= 70 => 'A',
                $cumulative <= 90 => 'B',
                default => 'C',
            };

            return [
                'product_id' => $item['product']->id,
                'product_name' => $item['product']->name,
                'sku' => $item['product']->sku,
                'annual_consumption' => $item['annual_qty'],
                'annual_value' => $item['annual_value'],
                'classification' => $classification,
            ];
        });
    }
}
```

#### Safety Stock Calculator

**`app/Services/Optimization/SafetyStockCalculator.php`**

```php
class SafetyStockCalculator
{
    /**
     * Calculate safety stock using the standard formula:
     * Safety Stock = Z * σ_d * √L
     *
     * Z = service level factor (1.65 for 95%, 2.33 for 99%)
     * σ_d = standard deviation of daily demand
     * L = lead time in days
     */
    public function calculate(Product $product, float $serviceLevel = 1.65): array
    {
        // Get daily demand for last 90 days
        $dailyData = app(ConsumptionDataCollector::class)
            ->getDailyConsumption($product, 90);

        $demands = array_column($dailyData, 'qty');
        $mean = count($demands) > 0 ? array_sum($demands) / count($demands) : 0;

        // Standard deviation
        $variance = 0;
        foreach ($demands as $d) {
            $variance += ($d - $mean) ** 2;
        }
        $stdDev = count($demands) > 1
            ? sqrt($variance / (count($demands) - 1))
            : 0;

        $leadTime = $product->lead_time_days ?? 7;
        $safetyStock = (int) ceil($serviceLevel * $stdDev * sqrt($leadTime));

        return [
            'mean_daily_demand' => round($mean, 2),
            'demand_std_dev' => round($stdDev, 2),
            'lead_time_days' => $leadTime,
            'service_level' => $serviceLevel,
            'safety_stock' => $safetyStock,
            'reorder_point' => (int) ceil($mean * $leadTime) + $safetyStock,
        ];
    }
}
```

#### EOQ Calculator

**`app/Services/Optimization/EoqCalculator.php`**

```php
class EoqCalculator
{
    /**
     * Economic Order Quantity:
     * EOQ = √(2DS/H)
     * D = annual demand, S = ordering cost, H = holding cost per unit per year
     */
    public function calculate(Product $product, float $orderingCost = 500, float $holdingCostRate = 0.20): array
    {
        $annualDemand = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('movement_type', 'issue')
            ->whereYear('performed_at', CarbonImmutable::now()->year)
            ->sum(DB::raw('ABS(qty_delta)'));

        $unitCost = $product->unit_price ?? 0;
        $holdingCost = $unitCost * $holdingCostRate;

        $eoq = $holdingCost > 0
            ? (int) ceil(sqrt((2 * $annualDemand * $orderingCost) / $holdingCost))
            : 0;

        // Optimal order frequency
        $ordersPerYear = $eoq > 0 ? $annualDemand / $eoq : 0;

        return [
            'annual_demand' => $annualDemand,
            'unit_cost' => $unitCost,
            'ordering_cost' => $orderingCost,
            'holding_cost_per_unit' => round($holdingCost, 2),
            'eoq' => $eoq,
            'orders_per_year' => round($ordersPerYear, 1),
            'days_between_orders' => $ordersPerYear > 0
                ? (int) round(365 / $ordersPerYear)
                : null,
        ];
    }
}
```

#### Scheduled Optimization Command

**`app/Console/Commands/RunInventoryOptimization.php`** — runs weekly:
```php
#[Signature('app:run-inventory-optimization')]
class RunInventoryOptimization extends Command
{
    public function handle(
        AbcAnalyzer $abc,
        SafetyStockCalculator $safety,
        EoqCalculator $eoq,
    ): void {
        // 1. ABC Analysis — store classifications on products
        $abcResults = $abc->analyze();
        foreach ($abcResults as $result) {
            Product::where('id', $result['product_id'])
                ->update(['abc_classification' => $result['classification']]);
        }

        // 2. Safety Stock & EOQ — update product recommendations
        Product::where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->chunkById(100, function ($products) use ($safety, $eoq) {
                foreach ($products as $product) {
                    $safetyCalc = $safety->calculate($product);
                    $eoqCalc = $eoq->calculate($product);

                    // Update product with recommended values
                    $update = [
                        'safety_stock' => $safetyCalc['safety_stock'],
                        'reorder_point' => $safetyCalc['reorder_point'],
                        'recommended_eoq' => $eoqCalc['eoq'],
                    ];

                    // Auto-update reorder_threshold if EOQ is calculated
                    if ($eoqCalc['eoq'] > 0) {
                        $update['reorder_threshold'] = $safetyCalc['reorder_point'];
                    }

                    Product::where('id', $product->id)->update($update);
                }
            });

        $this->info('Inventory optimization complete.');
    }
}
```

---

## 6. Multi-Level Approval Workflow Engine

**Impact:** MEDIUM-HIGH | **Effort:** 5-7 days | **Risk:** Medium

### Business Value
Currently, approval is single-level (Supply Head approves requisitions, Custodian approves bookings). Real organizations need routing: Dept Head → Supply Head → Admin, with escalation if someone is absent.

### Architecture

```
approval_workflows
├── id, name, model_type (Requisition|Booking)
├── is_active, priority
└── timestamps

approval_steps
├── id, approval_workflow_id, step_order
├── role_name (who must approve at this step)
├── escalation_hours (auto-escalate after N hours)
├── can_skip (if role doesn't exist, auto-approve)
└── timestamps

approval_instances
├── id, approval_workflow_id
├── approvable_type, approvable_id (polymorphic)
├── current_step, status (pending|approved|rejected|escalated)
└── timestamps

approval_actions
├── id, approval_instance_id, step_order
├── actor_id, action (approved|rejected|escalated)
├── notes, timestamps
```

### Key Implementation Steps

1. **Migration + Models** for `ApprovalWorkflow`, `ApprovalStep`, `ApprovalInstance`, `ApprovalAction`
2. **`app/Services/Workflow/ApprovalEngine.php`** — core engine:
   - `startWorkflow(Model $approvable, string $modelType)` — creates instance + notifies first approver
   - `processAction(ApprovalInstance $instance, User $actor, string $action, ?string $notes)` — records action, advances or completes
   - `escalate(ApprovalInstance $instance)` — moves to next step/approver
   - `checkForEscalations()` — scheduled command for timed escalations
3. **Replace hardcoded role checks** in `RequisitionController@approve` and `BookingController@approve` with the workflow engine
4. **Admin UI** at `/settings/approval-workflows` for configuring workflows per model type
5. **Frontend** — show approval timeline on requisition/booking show pages

### Escalation Scheduled Command

```php
#[Signature('app:process-approval-escalations')]
class ProcessApprovalEscalations extends Command
{
    public function handle(ApprovalEngine $engine): void
    {
        $staleInstances = ApprovalInstance::query()
            ->where('status', 'pending')
            ->whereHas('currentStep', fn ($q) =>
                $q->whereNotNull('escalation_hours')
                  ->whereRaw("NOW() > created_at + INTERVAL '1 hour' * escalation_hours")
            )
            ->get();

        foreach ($staleInstances as $instance) {
            $engine->escalate($instance);
        }
    }
}
```

---

## 7. Asset Lifecycle & Maintenance Tracking

**Impact:** MEDIUM | **Effort:** 4-5 days | **Risk:** Low

### Business Value
Assets need maintenance. Currently, the system tracks status and handovers but doesn't help schedule preventive maintenance or track repair history. This extends asset life and reduces unplanned downtime.

### Data Model

```
asset_maintenance_records
├── id, asset_id
├── type (preventive|corrective|inspection)
├── title, description
├── performed_by (user or vendor name)
├── performed_at, completed_at
├── cost (decimal)
├── status (scheduled|in_progress|completed)
├── next_maintenance_at (for recurring)
├── notes
└── timestamps
```

Add to `Asset` model:
```php
// New fillable fields:
'warranty_expires_at', 'purchase_date', 'purchase_cost',
'current_lifecycle_stage' => 'enum(procurement|deployment|maintenance|disposal)'
```

### Key Implementation Steps

1. Migration + Model + Controller (`AssetMaintenanceController`)
2. **Preventive Maintenance Scheduler** — calendar-based scheduling using existing FullCalendar integration
3. **Lifecycle Status Machine** — formal state transitions with required fields:
   - Procurement → Deployment (requires position assignment)
   - Deployment → Maintenance (creates maintenance record)
   - Maintenance → Deployment (requires completion sign-off)
   - Any → Disposal (requires admin approval + reason)
4. **Warranty Alert** — add to existing `app:inventory-generate-alerts`:
   ```php
   $expiringWarranties = Asset::whereNotNull('warranty_expires_at')
       ->whereDate('warranty_expires_at', '<=', $now->addDays(30))
       ->whereDate('warranty_expires_at', '>=', $now)
       ->get();
   ```
5. **Frontend** — maintenance calendar view, asset detail with lifecycle timeline

---

## 8. Department Budget Tracking & Financial Integration

**Impact:** MEDIUM-HIGH | **Effort:** 4-6 days | **Risk:** Medium

### Business Value
Gives department heads budget visibility and prevents overspending. Every requisition/issuance can be tracked against a budget, with alerts at configurable thresholds.

### Data Model

```
department_budgets
├── id, department_id, fiscal_year
├── allocated_amount, spent_amount (computed)
├── encumbered_amount (from approved but not yet issued)
├── created_by, approved_by
└── timestamps
```

Add `estimated_cost` to `RequisitionLine`:
```php
$table->decimal('estimated_cost', 10, 2)->nullable()->after('qty_issued');
```

### Key Implementation Steps

1. Migration + Model + Controller
2. **Budget Consumption Tracking** — listen to requisition events:
   ```php
   // When requisition is approved: encumber funds
   // When issued: move from encumbered to spent
   // When rejected: release encumbered funds
   ```
3. **Budget Alert** — add to `app:inventory-generate-alerts`:
   ```php
   $overBudget = DepartmentBudget::whereRaw('spent_amount > allocated_amount * 0.9')
       ->where('fiscal_year', CarbonImmutable::now()->year)
       ->get();
   ```
4. **Frontend** — budget widget on dashboard, budget bar on requisition create

---

## 9. In-App Notification Center & Preferences

**Impact:** MEDIUM | **Effort:** 3-4 days | **Risk:** Low

### Business Value
Currently, notifications are email-only. An in-app center gives users a single place to see all notifications, with read/unread state, prioritization, and preferences.

### Data Model

```
user_notifications
├── id, user_id, type (event class name)
├── title, body, action_url
├── read_at, created_at

notification_preferences
├── id, user_id
├── notification_type (requisition_approved|booking_submitted|low_stock|handover|etc)
├── channel (email|in_app|sms)
└── enabled
```

### ⚠️ Pre-verification Notes
- The `notifications` table migration does **NOT** exist — must run `php artisan notifications:table`
- All 6 existing notification classes only use `['mail']` in `via()` — all must be updated
- All 6 notification classes already have `toArray()` methods — already compatible with database channel
- No notification preferences infrastructure exists

### Implementation Strategy

Create the notifications table, then update existing notifications to use the `database` channel:

```bash
php artisan notifications:table
php artisan migrate
```

```php
// In existing notification classes, add 'database' channel:
public function via($notifiable): array
{
    return ['mail', 'database'];
}
```

### Key Steps

1. **Migration** for `notification_preferences` table
2. **`app/Services/NotificationPreferencesService.php`** — resolve user preferences per type
3. **Frontend** — notification dropdown in sidebar header (bell icon):
   ```vue
   <DropdownMenu>
       <DropdownMenuTrigger>
           <Button variant="ghost" size="icon" class="relative">
               <BellIcon class="h-5 w-5" />
               <span v-if="unreadCount > 0"
                     class="absolute -top-1 -right-1 h-4 w-4 rounded-full bg-red-500 text-[10px] text-white font-bold flex items-center justify-center">
                   {{ unreadCount > 9 ? '9+' : unreadCount }}
               </span>
           </Button>
       </DropdownMenuTrigger>
       <DropdownMenuContent align="end" class="w-80">
           <NotificationList :notifications="notifications" />
       </DropdownMenuContent>
   </DropdownMenu>
   ```
4. **Notification preferences page** — allow users to toggle email/in-app per event type
5. **Mark as read API** — `POST /notifications/{id}/read`

---

## 10. Advanced Security & Session Management

**Impact:** MEDIUM | **Effort:** 3-4 days | **Risk:** Low

### Business Value
Provides transparency and control over account access — critical for compliance and audit readiness.

### Implementation

#### Active Session Management

**`app/Http/Controllers/Settings/SessionsController.php`**:
```php
class SessionsController extends Controller
{
    public function index(Request $request): array
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->get()
            ->map(function ($session) {
                $payload = unserialize(base64_decode($session->payload));
                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'last_activity' => CarbonImmutable::createFromTimestamp($session->last_activity),
                    'is_current' => $session->id === Session::getId(),
                    'device' => $this->parseUserAgent($session->user_agent),
                ];
            });

        return ['sessions' => $sessions];
    }

    public function destroy(Request $request, string $sessionId): void
    {
        if ($sessionId === Session::getId()) {
            throw new \RuntimeException('Cannot delete current session.');
        }

        DB::table('sessions')->where('id', $sessionId)->where('user_id', $request->user()->id)->delete();
    }
}
```

#### Login History

**`database/migrations/xxxx_create_login_history_table.php`**:
```php
Schema::create('login_attempts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained();
    $table->string('email');
    $table->string('ip_address');
    $table->string('user_agent')->nullable();
    $table->boolean('success');
    $table->timestamp('attempted_at');
    $table->index(['user_id', 'attempted_at']);
});
```

Hook into Laravel auth events. Register in `AppServiceProvider@boot`:

> **Note:** `app/Providers/FortifyServiceProvider.php` does NOT currently use `Event::listen()`. The `AppServiceProvider@boot()` method uses a `configureDefaults()` pattern. Register login listeners directly in `boot()` after the existing code.

```php
// In app/Providers/AppServiceProvider.php@boot
public function boot(): void
{
    $this->configureDefaults();

    if (app()->isProduction()) {
        URL::forceScheme('https');
    }

    // --- NEW: Login attempt tracking ---
    Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
        LoginAttempt::create([
            'user_id' => $event->user?->id,
            'email' => $event->user?->email ?? request()->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'success' => true,
            'attempted_at' => now(),
        ]);
    });

    Event::listen(\Illuminate\Auth\Events\Failed::class, function ($event) {
        LoginAttempt::create([
            'user_id' => $event->user?->id,
            'email' => $event->credentials['email'] ?? 'unknown',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'success' => false,
            'attempted_at' => now(),
        ]);
    });
}
```

#### Frontend

- New settings page: **`resources/js/pages/settings/Sessions.vue`**
- New admin page: **`resources/js/pages/admin/LoginHistory.vue`**

---

## 11. Performance & Infrastructure Upgrades

**Impact:** HIGH | **Effort:** 3-5 days | **Risk:** Medium

### Business Value
Faster page loads, better concurrent user handling, and reduced database load. Critical as user count grows beyond the current UAT scale.

### Implementation Plan

#### A. Redis Cache Upgrade (1 day)

Replace `database` cache store with `redis`:

**`.env` changes:**
```ini
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

```bash
composer require predis/predis
```

**`config/cache.php`**:
```php
'default' => env('CACHE_STORE', 'redis'),

'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
    ],
],
```

**Already-cached optimizations** to make:
- `DashboardStatsService` — already caches at 60s, increase to 300s with Redis
- `Product reference cache` — already done per P2.5
- Add cache to: `ProductController@index` (paginated list, 60s TTL), `Category/Origin lists`, `Role/permission lookups`

#### B. Query Optimization Pass (1-2 days)

Identify slow queries using Laravel Debugbar or Telescope:

```bash
php artisan make:listener QueryPerformanceListener --event=Illuminate\Database\Events\QueryExecuted
```

Log queries that take > 100ms, then add composite indexes:

```php
// Migration for additional indexes:
Schema::table('stock_movements', function (Blueprint $table) {
    $table->index(['product_id', 'movement_type', 'performed_at']);
    $table->index(['performed_by', 'movement_type']);
});

Schema::table('requisition_lines', function (Blueprint $table) {
    $table->index(['requisition_id', 'product_id']);
});
```

#### C. Lazy Loading Detection (0.5 day)

Enable Laravel's N+1 detection in **`AppServiceProvider`**:
```php
public function boot(): void
{
    Model::preventLazyLoading(! $this->app->isProduction());
}
```

Review and fix any violations caught during testing.

#### D. Laravel Octane (Optional, 2 days)

For high-concurrency environments:
```bash
composer require laravel/octane
php artisan octane:install --server=swoole
```

Note: Octane requires careful review of singletons, deferred service providers, and request-local state. Test thoroughly before production.

---

## 12. Bulk Import/Export & Data Migration Tools

**Impact:** MEDIUM | **Effort:** 3-4 days | **Risk:** Medium

### Business Value
Large-scale data entry is painful through forms. Bulk import allows onboarding existing inventory data from spreadsheets — critical for initial deployment and periodic updates.

### Implementation

#### A. CSV/Excel Product Import

**`app/Http/Requests/Inventory/BulkImportProductsRequest.php`**:
```php
class BulkImportProductsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,xlsx,xls|max:5120',
            'has_header' => 'boolean',
            'update_existing' => 'boolean',
        ];
    }
}
```

**`app/Imports/ProductsImport.php`** (using Laravel Excel or raw CSV):
```php
class ProductsImport implements ToModel, WithValidation, WithHeadingRow
{
    public function model(array $row): Product
    {
        $category = Category::firstOrCreate(['name' => $row['category']]);
        $origin = Origin::firstOrCreate(['name' => $row['origin']]);

        return Product::updateOrCreate(
            ['sku' => $row['sku']],
            [
                'name' => $row['name'],
                'category_id' => $category->id,
                'origin_id' => $origin->id,
                'type' => ProductType::from($row['type']),
                'reorder_threshold' => (int) ($row['reorder_threshold'] ?? 0),
                'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'unit_price' => $row['unit_price'] ?? null,
                'lead_time_days' => $row['lead_time_days'] ?? null,
            ]
        );
    }

    public function rules(): array
    {
        return [
            'sku' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'type' => 'required|in:consumable,asset',
            'category' => 'required|string|max:255',
        ];
    }
}
```

#### B. Import Job Queue

For large files, process in chunks via a queued job:

```php
class ProcessProductImportChunk implements ShouldQueue
{
    public function __construct(
        private string $filePath,
        private int $offset,
        private int $limit,
    ) {}

    public function handle(): void
    {
        $rows = $this->readChunk($this->filePath, $this->offset, $this->limit);
        foreach ($rows as $row) {
            try {
                (new ProductsImport)->model($row);
            } catch (\Exception $e) {
                Log::error("Import row failed: {$e->getMessage()}", ['row' => $row]);
                // Store error for user feedback
                ImportError::create([
                    'import_batch_id' => $this->batchId,
                    'row' => json_encode($row),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
```

#### C. Export Enhancement

Extend existing report exports to support **XLSX format** (in addition to CSV/PDF):
```bash
composer require openspout/openspout
```

**`app/Services/Reports/XlsxTableExporter.php`**:
```php
class XlsxTableExporter
{
    public function export(TableReport $report, string $filename): string
    {
        $writer = new XLSXWriter();
        $writer->addSheet('Sheet1', $report->headers, $report->rows());
        $path = storage_path("app/exports/{$filename}.xlsx");
        $writer->writeToFile($path);
        return $path;
    }
}
```

#### D. Frontend — Import UI

New component **`resources/js/components/inventory/BulkImportDialog.vue`**:
```
- Drag & drop file area
- Column mapping preview (show first 5 rows)
- Validation error display per row
- Progress bar during processing
- Download error report after completion
```

---

## Prioritized Implementation Roadmap

| Phase | Features | Est. Effort | Expected Outcome |
|-------|----------|-------------|------------------|
| **Phase 1** (Immediate) | 1. Predictive Analytics<br>2. In-App Notification Center | 8-11 days | Data-driven reordering, reduced stockouts, better user engagement |
| **Phase 2** (Next Sprint) | 3. Supplier & PO Management<br>4. PWA + Barcode/QR<br>5. Inventory Optimization (ABC/EOQ) | 15-20 days | Complete procurement lifecycle, mobile field operations, scientific stock management |
| **Phase 3** (Following) | 6. Multi-Level Approvals<br>7. Asset Lifecycle & Maintenance | 9-12 days | Enterprise-grade workflow control, extended asset lifespan |
| **Phase 4** (Ongoing) | 8. Budget Tracking<br>9. Performance (Redis/Indexes)<br>10. Security & Sessions<br>11. Bulk Import/Export | 10-15 days | Financial accountability, faster response, audit readiness |
| **Phase 5** | Upgrade Polling → WebSocket (Reverb) | 4-6 days | Real-time collaboration, reduced latency |

**Total estimated effort:** 46-64 days for a single developer.

---

## Quick Wins (Can be done in 1-2 days each)

These are smaller, isolated features that can be implemented immediately while planning larger phases:

| # | Feature | Day | What to build |
|---|---------|-----|---------------|
| Q1 | **Slow-moving stock report** | 1 | Query products with zero movement in 6 months + Vue page |
| Q2 | **Product unit price field** | 0.5 | Add `unit_price` column to products, show in listing |
| Q3 | **Forecast widget on dashboard** | 1 | Show "predicted to stock out in X days" using existing data |
| Q4 | **Requisition copy/duplicate** | 0.5 | Button to create new requisition from existing one |
| Q5 | **Audit log search enhancement** | 1 | Add model-specific filters, date range presets |
| Q6 | **Export dashboard as PDF** | 1 | Screenshot dashboard + generate PDF report |
| Q7 | **Asset tag printable view** | 0.5 | Simple page with large QR code + tag code for printing |
| Q8 | **Session timeout warning enhancement** | 1 | Show countdown dialog, save draft before timeout |

---

## Files That Will Be Created/Modified (Summary)

### New Backend Files (~45 files)

```
app/
├── Console/Commands/
│   ├── GenerateDemandForecasts.php
│   ├── RunInventoryOptimization.php
│   └── ProcessApprovalEscalations.php
├── Events/
│   ├── RequisitionStatusChanged.php
│   ├── BookingStatusChanged.php
│   ├── StockLevelChanged.php
│   ├── NewInventoryAlert.php
│   └── HandoverInitiated.php
├── Exports/
│   └── ProductsExport.php
├── Http/Controllers/
│   ├── Api/ForecastController.php
│   ├── Inventory/SupplierController.php
│   ├── Inventory/PurchaseOrderController.php
│   ├── Inventory/CountController.php
│   ├── Inventory/MaintenanceRecordController.php
│   └── Settings/SessionsController.php
├── Imports/
│   └── ProductsImport.php
├── Models/
│   ├── ForecastProfile.php
│   ├── ForecastSnapshot.php
│   ├── Supplier.php
│   ├── PurchaseOrder.php
│   ├── PurchaseOrderLine.php
│   ├── InventoryCount.php
│   ├── CountLine.php
│   ├── AssetMaintenanceRecord.php
│   ├── DepartmentBudget.php
│   ├── ApprovalWorkflow.php
│   ├── ApprovalStep.php
│   ├── ApprovalInstance.php
│   └── LoginAttempt.php
├── Services/
│   ├── Forecasting/
│   │   ├── DemandForecaster.php
│   │   ├── ConsumptionDataCollector.php
│   │   ├── ReorderRecommender.php
│   │   └── Methods/
│   │       ├── MovingAverageMethod.php
│   │       └── ExponentialSmoothing.php
│   ├── Optimization/
│   │   ├── AbcAnalyzer.php
│   │   ├── SafetyStockCalculator.php
│   │   └── EoqCalculator.php
│   ├── Procurement/
│   │   └── PurchaseOrderGenerator.php
│   ├── Workflow/
│   │   └── ApprovalEngine.php
│   ├── Barcode/
│   │   └── BarcodeGeneratorService.php
│   └── NotificationPreferencesService.php
├── Events/
│   └── LoginAttemptListener.php
```

### New Frontend Files (~23 files)

```
resources/js/
├── echo.ts                                          ← NEW
├── composables/
│   ├── useRealtime.ts                               ← NEW
│   └── useNotificationCenter.ts                     ← NEW
├── components/
│   ├── inventory/ForecastWidget.vue                 ← NEW
│   ├── inventory/CountLineForm.vue                  ← NEW
│   ├── inventory/ApprovalTimeline.vue               ← NEW
│   ├── inventory/BulkImportDialog.vue               ← NEW
│   ├── NotificationBell.vue                         ← NEW
│   └── NotificationList.vue                         ← NEW
└── pages/
    ├── inventory/forecasting/Index.vue              ← NEW
    ├── inventory/forecasting/Show.vue               ← NEW
    ├── inventory/suppliers/{Index,Create,Show}.vue  ← NEW (3 pages)
    ├── inventory/purchase-orders/{Index,Create,Show}.vue ← NEW (3 pages)
    ├── inventory/counts/{Index,Create,Show}.vue     ← NEW (3 pages)
    ├── inventory/maintenance/{Index,Create,Show}.vue ← NEW (3 pages)
    ├── settings/Notifications.vue                   ← NEW
    ├── settings/Sessions.vue                        ← NEW
    └── admin/LoginHistory.vue                       ← NEW
```

### Modified Files (~23 files)

```
app/Http/Controllers/
├── DashboardController.php        ← Add forecast/supplier stats
├── Inventory/ProductController.php ← Add forecast to show()
├── Inventory/ReceivingController.php ← PO integration
├── Inventory/RequisitionController.php ← Fire events + workflow
├── Inventory/BookingController.php ← Fire events + workflow
├── Inventory/HandoverController.php ← Fire events

app/Http/Controllers/Api/
├── ProductController.php         ← Add barcode endpoints

app/Services/
├── DashboardStatsService.php     ← Add forecast/supplier/budget queries
├── NotificationService.php       ← Add user preferences
└── Inventory/InventoryService.php ← Fire StockLevelChanged event

app/Models/
├── Product.php                   ← Add supplier_id, unit_price, lead_time, abc_class
├── Asset.php                     ← Add warranty, lifecycle fields
└── User.php                      ← Add notificationPreferences relation

app/Notifications/ (6 files, all need via() update)
├── RequisitionSubmittedNotification.php       ← Add 'database' channel
├── RequisitionStatusChangedNotification.php   ← Add 'database' channel
├── BookingSubmittedNotification.php           ← Add 'database' channel
├── BookingStatusChangedNotification.php       ← Add 'database' channel
├── LowStockAlertNotification.php              ← Add 'database' channel
└── HandoverVerificationNotification.php       ← Add 'database' channel

app/Console/Commands/
└── InventoryGenerateAlerts.php   ← Add warranty/maintenance/budget alerts

routes/
├── console.php                   ← Add forecast/optimization schedules
└── channels.php                  ← NEW file for broadcast auth

config/
└── broadcasting.php              ← NEW (published via config:publish)

database/migrations/
└── (add 12+ new migration files)

resources/js/
├── composables/useQrScanner.ts   ← ENHANCE (already exists!)
├── components/inventory/QrScannerDialog.vue ← ENHANCE (already exists!)
├── pages/Dashboard.vue           ← Add forecast widget
├── pages/inventory/products/Show.vue ← Add forecast tab
├── layouts/app/AppHeaderLayout.vue ← Add notification bell
└── layouts/app/AppSidebarLayout.vue ← Add notification count
```

---

> **Note:** All implementations follow your existing patterns — Wayfinder for typed routes, Inertia + Vue 3 for frontend, Pest for tests, Pint for formatting. Each phase should be followed by `php artisan test --compact` and `npm run build` to verify nothing breaks.
