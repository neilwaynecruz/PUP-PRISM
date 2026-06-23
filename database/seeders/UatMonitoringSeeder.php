<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\ForecastSnapshot;
use App\Models\HandoverLog;
use App\Models\InventoryAlert;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\RequisitionTemplate;
use App\Models\User;
use App\Notifications\BookingStatusChangedNotification;
use App\Notifications\HandoverVerificationNotification;
use App\Notifications\LowStockAlertNotification;
use App\Notifications\RequisitionStatusChangedNotification;
use App\Notifications\RequisitionSubmittedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class UatMonitoringSeeder extends Seeder
{
    public function run(): void
    {
        $now = CarbonImmutable::today()->setTime(15, 0);

        /** @var array<string, User> $users */
        $users = User::query()->get()->keyBy('email')->all();
        /** @var array<string, Product> $products */
        $products = Product::withTrashed()->get()->keyBy('sku')->all();
        /** @var array<string, Booking> $bookings */
        $bookings = Booking::withTrashed()->get()->keyBy('purpose')->all();
        /** @var array<string, Requisition> $requisitions */
        $requisitions = Requisition::withTrashed()->get()->keyBy('notes')->all();
        $pendingHandover = HandoverLog::query()
            ->where('notes', '[UAT] Pending director office projector handover')
            ->firstOrFail();

        $this->seedTemplate(
            $users['library.custodian@local.test'],
            'Library monthly replenishment',
            'Baseline template for paper, ink, and emergency cleaning stocks.',
            [
                ['sku' => 'CON-PAPER-A4', 'name' => 'Bond Paper A4', 'qty_requested' => 20],
                ['sku' => 'CON-INK-BLK', 'name' => 'Printer Ink Black', 'qty_requested' => 4],
                ['sku' => 'CON-DISINF-01', 'name' => 'Surface Disinfectant', 'qty_requested' => 3],
            ],
        );

        $this->seedTemplate(
            $users['eng.custodian@local.test'],
            'Engineering field kit',
            'Quick-issue template for laboratory and field deployment needs.',
            [
                ['sku' => 'CON-BAT-AA', 'name' => 'Alkaline Battery AA', 'qty_requested' => 6],
                ['sku' => 'CON-GLOVES-M', 'name' => 'Nitrile Gloves Medium', 'qty_requested' => 4],
                ['sku' => 'CON-ALCOHOL-70', 'name' => 'Isopropyl Alcohol 70%', 'qty_requested' => 2],
            ],
        );

        $this->seedTemplate(
            $users['director.office.custodian@local.test'],
            'Catalog review edge cases',
            'Used to exercise inactive and removed SKU handling in the template picker.',
            [
                ['sku' => 'CON-FILEBOX-01', 'name' => 'Archive File Box', 'qty_requested' => 5],
                ['sku' => 'CON-MARKER-BLK', 'name' => 'Permanent Marker Black', 'qty_requested' => 3],
            ],
        );

        ForecastSnapshot::query()
            ->whereDate('forecast_date', $now->toDateString())
            ->delete();

        Artisan::call('app:generate-demand-forecasts', [
            '--date' => $now->toDateString(),
        ]);
        Artisan::call('app:inventory-generate-alerts');

        $paper = $products['CON-PAPER-A4'];
        $marker = $products['CON-MARKER-BLK'];

        InventoryAlert::query()->updateOrCreate(
            [
                'type' => 'low_stock',
                'product_id' => $paper->id,
                'stock_lot_id' => null,
                'resolved_at' => $now->subDays(3),
            ],
            [
                'message' => 'Resolved UAT example: bond paper stock recovered after emergency replenishment.',
                'detected_at' => $now->subDays(5),
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

        $this->seedNotification(
            'uat-low-stock-supply-head',
            $users['supply@local.test'],
            new LowStockAlertNotification($products['CON-DISINF-01'], 8),
            $now->subMinutes(45),
        );

        $this->seedNotification(
            'uat-requisition-submitted-supply-head',
            $users['supply@local.test'],
            new RequisitionSubmittedNotification($requisitions['[UAT] Submitted director office sanitation request']),
            $now->subHours(2),
            $now->subHour(),
        );

        $this->seedNotification(
            'uat-requisition-issued-engineering',
            $users['eng.custodian@local.test'],
            new RequisitionStatusChangedNotification($requisitions['[UAT] Issued engineering field kit replenishment'], 'issued'),
            $now->subHours(6),
        );

        $this->seedNotification(
            'uat-booking-rejected-engineering',
            $users['eng.custodian@local.test'],
            new BookingStatusChangedNotification($bookings['[UAT] Conflicting booking example'], 'rejected'),
            $now->subHours(4),
            $now->subHours(3),
        );

        $this->seedNotification(
            'uat-handover-director-office',
            $users['director.office.custodian@local.test'],
            new HandoverVerificationNotification($pendingHandover->id, 'uat-preview-token'),
            $now->subMinutes(25),
        );

        $this->seedAuditLog(
            userId: $users['admin@local.test']->id,
            action: 'seed',
            modelType: 'DatabaseSeeder',
            modelId: null,
            description: 'Executed comprehensive UAT database seed.',
            createdAt: $now->subMinutes(10),
        );

        $this->seedAuditLog(
            userId: $users['supply@local.test']->id,
            action: 'forecast_generate',
            modelType: 'Product',
            modelId: $products['CON-DISINF-01']->id,
            description: 'Generated demand forecast for Surface Disinfectant.',
            newValues: ['sku' => 'CON-DISINF-01'],
            createdAt: $now->subMinutes(8),
        );

        $this->seedAuditLog(
            userId: $users['supply@local.test']->id,
            action: 'alert_detected',
            modelType: 'Product',
            modelId: $products['CON-GLOVES-M']->id,
            description: 'Low stock alert detected for Nitrile Gloves Medium.',
            newValues: ['type' => 'low_stock'],
            createdAt: $now->subMinutes(7),
        );

        $this->seedAuditLog(
            userId: $users['custodian@local.test']->id,
            action: 'handover_initiated',
            modelType: 'Asset',
            modelId: $products['AST-LAP-001']->assets()->where('tag_code', 'AST-COE-0001')->value('id'),
            description: 'Initiated engineering laptop accountability handover.',
            createdAt: $now->subMinutes(6),
        );

        $this->seedAuditLog(
            userId: $users['supply@local.test']->id,
            action: 'template_curated',
            modelType: 'RequisitionTemplate',
            modelId: RequisitionTemplate::query()->where('name', 'Library monthly replenishment')->value('id'),
            description: 'Updated library replenishment requisition template.',
            createdAt: $now->subMinutes(5),
        );
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
                'user_agent' => 'UAT Seeder',
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
