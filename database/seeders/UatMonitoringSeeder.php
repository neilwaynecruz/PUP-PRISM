<?php

namespace Database\Seeders;

use App\Enums\NotificationEventType;
use App\Enums\ProductType;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\ForecastProfile;
use App\Models\ForecastSnapshot;
use App\Models\HandoverLog;
use App\Models\InventoryAlert;
use App\Models\NotificationPreference;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\RequisitionTemplate;
use App\Models\User;
use App\Notifications\BookingStatusChangedNotification;
use App\Notifications\BookingSubmittedNotification;
use App\Notifications\ExpiringStockAlertNotification;
use App\Notifications\HandoverVerificationNotification;
use App\Notifications\LowStockAlertNotification;
use App\Notifications\ProcurementRecommendationNotification;
use App\Notifications\RequisitionStatusChangedNotification;
use App\Notifications\RequisitionSubmittedNotification;
use App\Services\DashboardStatsCache;
use App\Services\Forecasting\DemandForecaster;
use App\Services\NotificationPreferenceSeeder;
use App\Services\Optimization\InventoryOptimizationCache;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class UatMonitoringSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Operational seed data cannot be seeded in production.');
        }

        $now = CarbonImmutable::today()->setTime(15, 0);

        /** @var array<string, User> $users */
        $users = User::query()->get()->keyBy('email')->all();
        /** @var array<string, Product> $products */
        $products = Product::withTrashed()->get()->keyBy('sku')->all();
        /** @var array<string, Booking> $bookings */
        $bookings = Booking::withTrashed()->with(['requester', 'asset'])->get()->keyBy('purpose')->all();
        /** @var array<string, Requisition> $requisitions */
        $requisitions = Requisition::withTrashed()->with('requester')->get()->keyBy('notes')->all();
        $pendingHandover = HandoverLog::query()
            ->where('notes', 'Pending director office projector handover')
            ->firstOrFail();

        $this->seedTemplate(
            $users['nathanweiltcruz1415@gmail.com'],
            'Library monthly replenishment',
            'Baseline template for paper, ink, and emergency cleaning stocks.',
            [
                ['sku' => 'CON-PAPER-A4', 'name' => 'Multipurpose Copy Paper, A4, 80 gsm, 500 Sheets/Ream', 'qty_requested' => 20],
                ['sku' => 'CON-INK-BLK', 'name' => 'Black Printer Ink Bottle, 135 mL', 'qty_requested' => 4],
                ['sku' => 'CON-DISINF-01', 'name' => 'Ready-to-Use Surface Disinfectant, 1 Liter Bottle', 'qty_requested' => 3],
            ],
        );

        $this->seedTemplate(
            $users['norman.cruz0701@gmail.com'],
            'Engineering field kit',
            'Quick-issue template for laboratory and field deployment needs.',
            [
                ['sku' => 'CON-BAT-AA', 'name' => 'AA Alkaline Battery, 1.5 V, 4 Pieces/Pack', 'qty_requested' => 6],
                ['sku' => 'CON-GLOVES-M', 'name' => 'Nitrile Examination Gloves, Medium, 100 Pieces/Box', 'qty_requested' => 4],
                ['sku' => 'CON-ALCOHOL-70', 'name' => '70% Isopropyl Alcohol, 500 mL Bottle', 'qty_requested' => 2],
            ],
        );

        $this->seedTemplate(
            $users['wendzcruz04@gmail.com'],
            'Records archiving supplies',
            'Retained for authorized records work while older catalog items remain in historical requests.',
            [
                ['sku' => 'CON-FILEBOX-01', 'name' => 'Acid-Free Archive File Box, Legal Size', 'qty_requested' => 5],
                ['sku' => 'CON-MARKER-BLK', 'name' => 'Permanent Marker, Black, Bullet Tip', 'qty_requested' => 3],
            ],
        );

        /** @var array<string, array{method: string, lookback: int, horizon: int, lead_time: int, safety_stock: int}> $forecastProfiles */
        $forecastProfiles = [
            'CON-PAPER-A4' => ['method' => 'exponential_smoothing', 'lookback' => 90, 'horizon' => 60, 'lead_time' => 7, 'safety_stock' => 7],
            'CON-INK-BLK' => ['method' => 'exponential_smoothing', 'lookback' => 90, 'horizon' => 60, 'lead_time' => 10, 'safety_stock' => 7],
            'CON-ALCOHOL-70' => ['method' => 'seasonal', 'lookback' => 365, 'horizon' => 45, 'lead_time' => 7, 'safety_stock' => 7],
            'CON-BAT-AA' => ['method' => 'exponential_smoothing', 'lookback' => 90, 'horizon' => 60, 'lead_time' => 7, 'safety_stock' => 7],
            'CON-DISINF-01' => ['method' => 'exponential_smoothing', 'lookback' => 90, 'horizon' => 45, 'lead_time' => 10, 'safety_stock' => 7],
            'CON-GLOVES-M' => ['method' => 'exponential_smoothing', 'lookback' => 90, 'horizon' => 60, 'lead_time' => 10, 'safety_stock' => 7],
            'CON-HSOAP-500' => ['method' => 'moving_average', 'lookback' => 90, 'horizon' => 45, 'lead_time' => 7, 'safety_stock' => 7],
            'OFF-PAPER-A4-80' => ['method' => 'exponential_smoothing', 'lookback' => 90, 'horizon' => 60, 'lead_time' => 7, 'safety_stock' => 7],
            'OFF-PAPER-LONG-80' => ['method' => 'exponential_smoothing', 'lookback' => 90, 'horizon' => 60, 'lead_time' => 7, 'safety_stock' => 7],
            'OFF-PEN-BLU' => ['method' => 'moving_average', 'lookback' => 90, 'horizon' => 60, 'lead_time' => 7, 'safety_stock' => 7],
            'OFF-PEN-BLA' => ['method' => 'exponential_smoothing', 'lookback' => 90, 'horizon' => 60, 'lead_time' => 7, 'safety_stock' => 7],
            'OFF-MARK-BLA-REF' => ['method' => 'moving_average', 'lookback' => 90, 'horizon' => 60, 'lead_time' => 7, 'safety_stock' => 7],
            'OFF-LAS-BLA' => ['method' => 'exponential_smoothing', 'lookback' => 90, 'horizon' => 60, 'lead_time' => 10, 'safety_stock' => 7],
        ];

        foreach ($forecastProfiles as $forecastSku => $profile) {
            ForecastProfile::query()->updateOrCreate(
                ['product_id' => $products[$forecastSku]->id],
                [
                    'method' => $profile['method'],
                    'lookback_days' => $profile['lookback'],
                    'forecast_horizon_days' => $profile['horizon'],
                    'lead_time_days' => $profile['lead_time'],
                    'safety_stock_days' => $profile['safety_stock'],
                    'smoothing_factor' => $profile['method'] === 'exponential_smoothing' ? 0.35 : null,
                    'trend_factor' => $profile['method'] === 'exponential_smoothing' ? 0.15 : null,
                    'is_active' => true,
                ],
            );
        }

        $todayEnd = $now->endOfDay();
        $forecaster = app(DemandForecaster::class);
        $activeConsumableIds = Product::query()
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->pluck('id');

        ForecastSnapshot::query()
            ->whereIn('product_id', $activeConsumableIds)
            ->whereDate('forecast_date', $now->toDateString())
            ->delete();

        foreach ($forecaster->forecastAll($todayEnd) as $productId => $result) {
            ForecastSnapshot::query()->updateOrCreate(
                [
                    'product_id' => $productId,
                    'forecast_date' => $result->generatedAt->startOfDay(),
                ],
                $result->toSnapshotPayload(),
            );
        }

        $this->seedNotificationPreferences($users);

        $paper = $products['CON-PAPER-A4'];
        $marker = $products['CON-MARKER-BLK'];
        $disinfectantLot = $products['CON-DISINF-01']->lots()->with('product')->where('reference_no', 'DR-2026-02347')->firstOrFail();
        $expiredMaskLot = $products['CON-MASK-3PLY']->lots()->with('product')->where('reference_no', 'DR-2025-04182')->firstOrFail();

        InventoryAlert::query()->updateOrCreate(
            [
                'type' => 'low_stock',
                'product_id' => $paper->id,
                'stock_lot_id' => null,
                'message' => 'Bond paper stock returned to the approved operating level after emergency replenishment.',
            ],
            [
                'detected_at' => $now->subDays(5),
                'resolved_at' => $now->subDays(3),
            ],
        );

        InventoryAlert::query()->updateOrCreate(
            [
                'type' => 'low_stock',
                'product_id' => $products['CON-HSOAP-500']->id,
                'stock_lot_id' => null,
                'resolved_at' => null,
            ],
            [
                'message' => 'Antibacterial liquid hand soap is out of stock. Replenishment is required before the next facilities issue schedule.',
                'detected_at' => $now->subHours(3),
                'acknowledged_at' => $now->subHours(2),
                'acknowledged_by' => $users['norman.cruz0701@gmail.com']->id,
                'assigned_to' => $users['wendzcruz04@gmail.com']->id,
                'resolved_by' => null,
                'resolution_notes' => null,
            ],
        );

        InventoryAlert::query()->updateOrCreate(
            [
                'type' => 'expiring',
                'product_id' => $products['CON-DISINF-01']->id,
                'stock_lot_id' => $disinfectantLot->id,
                'resolved_at' => null,
            ],
            [
                'message' => 'Disinfectant lot DR-2026-02347 expires within seven days. Issue this lot first under FEFO.',
                'detected_at' => $now->subHours(4),
                'acknowledged_at' => null,
                'acknowledged_by' => null,
                'assigned_to' => $users['norman.cruz0701@gmail.com']->id,
                'resolved_by' => null,
                'resolution_notes' => null,
            ],
        );

        InventoryAlert::query()->updateOrCreate(
            [
                'type' => 'expired',
                'product_id' => $products['CON-MASK-3PLY']->id,
                'stock_lot_id' => $expiredMaskLot->id,
                'message' => 'Expired face mask lot DR-2025-04182 was removed from issuable stock.',
            ],
            [
                'detected_at' => $now->subDays(16),
                'acknowledged_at' => $now->subDays(16)->addHour(),
                'acknowledged_by' => $users['norman.cruz0701@gmail.com']->id,
                'assigned_to' => $users['wendzcruz04@gmail.com']->id,
                'resolved_at' => $now->subDays(14),
                'resolved_by' => $users['wendzcruz04@gmail.com']->id,
                'resolution_notes' => 'Quantity adjusted to zero and retained in the expired-lot history for disposal tracking.',
            ],
        );

        InventoryAlert::query()->updateOrCreate(
            [
                'type' => 'catalog_retired',
                'product_id' => $marker->id,
                'stock_lot_id' => null,
                'resolved_at' => null,
            ],
            [
                'message' => 'Catalog item retained only in trash for historical template references.',
                'detected_at' => $now->subDays(2),
            ],
        );

        InventoryAlert::query()->updateOrCreate(
            [
                'type' => 'forecast_stockout',
                'product_id' => $paper->id,
                'stock_lot_id' => null,
                'resolved_at' => null,
            ],
            [
                'message' => 'A4 multipurpose copy paper is projected to stock out within 7 days at current campus print demand.',
                'detected_at' => $now->subHours(2),
                'acknowledged_at' => null,
                'acknowledged_by' => null,
                'assigned_to' => $users['wendzcruz04@gmail.com']->id,
                'resolved_by' => null,
                'resolution_notes' => null,
            ],
        );

        InventoryAlert::query()->updateOrCreate(
            [
                'type' => 'forecast_stockout',
                'product_id' => $products['OFF-PAPER-A4-80']->id,
                'stock_lot_id' => null,
                'resolved_at' => null,
            ],
            [
                'message' => 'Bond Paper A4 80 gsm stock is projected to stock out within 5 days; reorder suggested.',
                'detected_at' => $now->subHour(),
                'acknowledged_at' => null,
                'acknowledged_by' => null,
                'assigned_to' => $users['wendzcruz04@gmail.com']->id,
                'resolved_by' => null,
                'resolution_notes' => null,
            ],
        );

        $this->seedNotification(
            'operations-low-stock-supply-head',
            $users['norman.cruz0701@gmail.com'],
            new LowStockAlertNotification($products['CON-DISINF-01'], 8),
            $now->subMinutes(45),
        );

        $this->seedNotification(
            'operations-requisition-submitted-supply-head',
            $users['wendzcruz04@gmail.com'],
            new RequisitionSubmittedNotification($requisitions['Submitted director office sanitation request']),
            $now->subHours(2),
            $now->subHour(),
        );

        $this->seedNotification(
            'operations-requisition-issued-engineering',
            $users['nathanweiltcruz1415@gmail.com'],
            new RequisitionStatusChangedNotification($requisitions['Issued engineering field kit replenishment'], 'issued'),
            $now->subHours(6),
        );

        $this->seedNotification(
            'operations-booking-rejected-engineering',
            $users['nathanweiltcruz1415@gmail.com'],
            new BookingStatusChangedNotification($bookings['Engineering accreditation presentation'], 'rejected'),
            $now->subHours(4),
            $now->subHours(3),
        );

        $this->seedNotification(
            'operations-handover-director-office',
            $users['nathanweiltcruz1415@gmail.com'],
            new HandoverVerificationNotification($pendingHandover->id, 'pending-projector-handover-token'),
            $now->subMinutes(25),
        );

        $this->seedNotification(
            'operations-booking-submitted-supply-head',
            $users['norman.cruz0701@gmail.com'],
            new BookingSubmittedNotification($bookings['Library digital orientation']),
            $now->subHours(3),
        );

        $this->seedNotification(
            'operations-expiring-disinfectant-supply-head',
            $users['norman.cruz0701@gmail.com'],
            new ExpiringStockAlertNotification($disinfectantLot, 4),
            $now->subHours(4),
        );

        $this->seedNotification(
            'operations-procurement-recommendation-supply-head',
            $users['wendzcruz04@gmail.com'],
            new ProcurementRecommendationNotification($products['CON-PAPER-A4'], 5, 64),
            $now->subHours(5),
            $now->subHours(4),
        );

        // Institutional Admin Notifications
        $this->seedNotification(
            'operations-admin-low-stock-soap',
            $users['neilwayne0514@gmail.com'],
            new LowStockAlertNotification($products['CON-HSOAP-500'], 0),
            $now->subHours(3),
        );

        $this->seedNotification(
            'operations-admin-requisition-submitted',
            $users['neilwayne0514@gmail.com'],
            new RequisitionSubmittedNotification($requisitions['Submitted director office sanitation request']),
            $now->subHours(2),
        );

        $this->seedNotification(
            'operations-admin-booking-submitted',
            $users['neilwayne0514@gmail.com'],
            new BookingSubmittedNotification($bookings['Library digital orientation']),
            $now->subHours(3),
            $now->subHour(),
        );

        $this->seedNotification(
            'operations-admin-procurement-recommendation',
            $users['neilwayne0514@gmail.com'],
            new ProcurementRecommendationNotification($products['CON-PAPER-A4'], 5, 64),
            $now->subHours(5),
            $now->subHours(4),
        );

        // Logistics Coordinator / Property Custodian Notifications
        $this->seedNotification(
            'operations-custodian-booking-submitted',
            $users['nathanweiltcruz1415@gmail.com'],
            new BookingSubmittedNotification($bookings['Library digital orientation']),
            $now->subHours(3),
        );

        $this->seedNotification(
            'operations-custodian-requisition-submitted',
            $users['wendzcruz04@gmail.com'],
            new RequisitionSubmittedNotification($requisitions['Submitted director office sanitation request']),
            $now->subHours(2),
        );

        $this->seedNotification(
            'operations-custodian-handover-verification',
            $users['nathanweiltcruz1415@gmail.com'],
            new HandoverVerificationNotification($pendingHandover->id, 'pending-projector-handover-token'),
            $now->subMinutes(20),
        );

        $this->seedNotification(
            'operations-custodian-booking-approved',
            $users['cruzneilwayne@gmail.com'],
            new BookingStatusChangedNotification($bookings['Director briefing and equipment walkthrough'], 'approved'),
            $now->subHours(5),
            $now->subHours(4),
        );

        // IT Director Notifications
        $this->seedNotification(
            'operations-it-low-stock-disinfectant',
            $users['cruzneilwayne@gmail.com'],
            new LowStockAlertNotification($products['CON-DISINF-01'], 8),
            $now->subHours(1),
        );

        $this->seedAuditLog(
            userId: $users['neilwayne0514@gmail.com']->id,
            action: 'seed',
            modelType: 'DatabaseSeeder',
            modelId: null,
            description: 'Initialized baseline institutional operations dataset.',
            createdAt: $now->subMinutes(10),
        );

        $this->seedAuditLog(
            userId: $users['wendzcruz04@gmail.com']->id,
            action: 'forecast_generate',
            modelType: 'Product',
            modelId: $products['CON-DISINF-01']->id,
            description: 'Generated demand forecast for Ready-to-Use Surface Disinfectant, 1 Liter Bottle.',
            newValues: ['sku' => 'CON-DISINF-01'],
            createdAt: $now->subMinutes(8),
        );

        $this->seedAuditLog(
            userId: $users['norman.cruz0701@gmail.com']->id,
            action: 'alert_detected',
            modelType: 'Product',
            modelId: $products['CON-GLOVES-M']->id,
            description: 'Low stock alert detected for Nitrile Examination Gloves, Medium, 100 Pieces/Box.',
            newValues: ['type' => 'low_stock'],
            createdAt: $now->subMinutes(7),
        );

        $this->seedAuditLog(
            userId: $users['nathanweiltcruz1415@gmail.com']->id,
            action: 'handover_initiated',
            modelType: 'Asset',
            modelId: $products['AST-LAP-001']->assets()->where('tag_code', 'AST-COE-0001')->value('id'),
            description: 'Initiated engineering laptop accountability handover.',
            createdAt: $now->subMinutes(6),
        );

        $this->seedAuditLog(
            userId: $users['wendzcruz04@gmail.com']->id,
            action: 'template_curated',
            modelType: 'RequisitionTemplate',
            modelId: RequisitionTemplate::query()->where('name', 'Library monthly replenishment')->value('id'),
            description: 'Updated library replenishment requisition template.',
            createdAt: $now->subMinutes(5),
        );

        app(DashboardStatsCache::class)->invalidate();
        app(InventoryOptimizationCache::class)->invalidate();
    }

    /**
     * @param  array<int, array{sku: string, name: string, qty_requested: int}>  $lines
     */
    private function seedTemplate(User $user, string $name, ?string $notes, array $lines): void
    {
        RequisitionTemplate::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'name' => $name,
            ],
            [
                'notes' => $notes,
                'lines' => $lines,
            ],
        );
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedNotificationPreferences(array $users): void
    {
        $preferenceSeeder = app(NotificationPreferenceSeeder::class);

        foreach ($users as $user) {
            $preferenceSeeder->seedForUser($user);
        }

        NotificationPreference::query()->updateOrCreate(
            [
                'user_id' => $users['norman.cruz0701@gmail.com']->id,
                'event_type' => NotificationEventType::LowStock->value,
            ],
            [
                'mail_enabled' => true,
                'database_enabled' => true,
                'broadcast_enabled' => true,
                'digest_frequency' => 'daily',
            ],
        );

        NotificationPreference::query()->updateOrCreate(
            [
                'user_id' => $users['wendzcruz04@gmail.com']->id,
                'event_type' => NotificationEventType::ProcurementRecommendation->value,
            ],
            [
                'mail_enabled' => true,
                'database_enabled' => true,
                'broadcast_enabled' => true,
                'digest_frequency' => 'daily',
            ],
        );
    }

    private function seedNotification(
        string $key,
        User $user,
        Notification $notification,
        CarbonImmutable $createdAt,
        ?CarbonImmutable $readAt = null,
    ): void {
        $notificationData = method_exists($notification, 'toArray')
            ? $notification->toArray($user)
            : (method_exists($notification, 'toDatabase') ? $notification->toDatabase($user) : []);

        DB::table('notifications')->updateOrInsert(
            ['id' => $this->deterministicUuid($key)],
            [
                'type' => $notification::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode($notificationData, JSON_THROW_ON_ERROR),
                'read_at' => $readAt,
                'created_at' => $createdAt,
                'updated_at' => $readAt ?? $createdAt,
            ],
        );
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function seedAuditLog(
        ?int $userId,
        string $action,
        string $modelType,
        ?int $modelId,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?CarbonImmutable $createdAt = null,
    ): void {
        $createdAt ??= CarbonImmutable::now();

        AuditLog::query()->updateOrCreate(
            [
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'description' => $description,
            ],
            [
                'user_id' => $userId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PRISM Database Seeder',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ],
        );
    }

    private function deterministicUuid(string $seed): string
    {
        $hash = md5($seed);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }
}
