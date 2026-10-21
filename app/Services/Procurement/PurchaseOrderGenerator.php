<?php

namespace App\Services\Procurement;

use App\Enums\ProductType;
use App\Enums\PurchaseOrderStatus;
use App\Models\ForecastSnapshot;
use App\Models\InventoryAlert;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseOrderGenerator
{
    public function __construct(
        private readonly PurchaseOrderNumberGenerator $numberGenerator,
    ) {}

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function generateFromAlerts(User $requestedBy): Collection
    {
        $productsTable = (new Product)->getTable();

        /** @var Collection<int|string, Collection<int, Product>> $products */
        $products = Product::query()
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->whereNotNull('supplier_id')
            ->whereHas('stock', fn (Builder $query) => $query->whereColumn('on_hand_qty', '<=', "{$productsTable}.reorder_threshold"))
            ->whereDoesntHave('purchaseOrderLines.purchaseOrder', fn (Builder $query) => $query->whereIn('status', [
                PurchaseOrderStatus::Draft,
                PurchaseOrderStatus::Sent,
                PurchaseOrderStatus::Partial,
            ]))
            ->with([
                'stock:id,product_id,on_hand_qty',
                'supplier:id,name,lead_time_days,is_active',
            ])
            ->get()
            ->filter(fn (Product $product): bool => $product->supplier?->is_active === true)
            ->groupBy('supplier_id');

        $created = new Collection;

        foreach ($products as $supplierProducts) {
            $purchaseOrder = DB::transaction(fn (): ?PurchaseOrder => $this->createDraftPurchaseOrder(
                $requestedBy,
                $supplierProducts->values(),
            ));

            if ($purchaseOrder instanceof PurchaseOrder) {
                $created->push($purchaseOrder);
            }
        }

        return $created;
    }

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function generateFromForecastAlerts(User $requestedBy): Collection
    {
        $productsTable = (new Product)->getTable();
        $forecastProductIds = InventoryAlert::query()
            ->where('type', 'forecast_stockout')
            ->whereNull('resolved_at')
            ->pluck('product_id')
            ->unique()
            ->values();

        if ($forecastProductIds->isEmpty()) {
            return new Collection;
        }

        /** @var Collection<int|string, Collection<int, Product>> $products */
        $products = Product::query()
            ->whereIn('id', $forecastProductIds)
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->whereNotNull('supplier_id')
            ->whereHas('stock', fn (Builder $query) => $query->whereColumn('on_hand_qty', '>', "{$productsTable}.reorder_threshold"))
            ->whereDoesntHave('purchaseOrderLines.purchaseOrder', fn (Builder $query) => $query->whereIn('status', [
                PurchaseOrderStatus::Draft,
                PurchaseOrderStatus::Sent,
                PurchaseOrderStatus::Partial,
            ]))
            ->with([
                'stock:id,product_id,on_hand_qty',
                'supplier:id,name,lead_time_days,is_active',
            ])
            ->get()
            ->filter(fn (Product $product): bool => $product->supplier?->is_active === true)
            ->groupBy('supplier_id');

        $created = new Collection;

        foreach ($products as $supplierProducts) {
            $purchaseOrder = DB::transaction(fn (): ?PurchaseOrder => $this->createDraftPurchaseOrder(
                $requestedBy,
                $supplierProducts->values(),
                'Auto-generated from forecast stockout alerts.',
            ));

            if ($purchaseOrder instanceof PurchaseOrder) {
                $created->push($purchaseOrder);
            }
        }

        return $created;
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function createDraftPurchaseOrder(
        User $requestedBy,
        Collection $products,
        string $notes = 'Auto-generated from low-stock and forecast signals.',
    ): ?PurchaseOrder {
        $productIds = $products->modelKeys();
        sort($productIds);

        if ($productIds === []) {
            return null;
        }

        Product::query()
            ->whereKey($productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        $products = Product::query()
            ->whereKey($productIds)
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->whereNotNull('supplier_id')
            ->whereDoesntHave('purchaseOrderLines.purchaseOrder', fn (Builder $query) => $query->whereIn('status', [
                PurchaseOrderStatus::Draft,
                PurchaseOrderStatus::Sent,
                PurchaseOrderStatus::Partial,
            ]))
            ->with([
                'stock:id,product_id,on_hand_qty',
                'supplier:id,name,lead_time_days,is_active',
            ])
            ->orderBy('id')
            ->get()
            ->filter(fn (Product $product): bool => $product->supplier?->is_active === true)
            ->values();

        /** @var Product|null $firstProduct */
        $firstProduct = $products->first();

        if (! $firstProduct instanceof Product || $firstProduct->supplier_id === null) {
            return null;
        }

        $products = $products
            ->filter(fn (Product $product) => $product->supplier_id === $firstProduct->supplier_id)
            ->values();

        $lines = [];
        $subtotal = 0.0;
        $expectedDeliveryAt = null;

        foreach ($products as $product) {
            $recommendedQty = $this->resolveRecommendedQuantity($product);

            if ($recommendedQty <= 0) {
                continue;
            }

            $unitPrice = round((float) ($product->unit_price ?? 0), 2);
            $lineSubtotal = round($unitPrice * $recommendedQty, 2);
            $expectedDeliveryAt ??= now()->addDays(
                max(1, (int) ($product->lead_time_days ?? $product->supplier?->lead_time_days ?? 7)),
            );

            $lines[] = [
                'product_id' => $product->id,
                'qty_ordered' => $recommendedQty,
                'qty_received' => 0,
                'unit_price' => $unitPrice,
                'subtotal' => $lineSubtotal,
            ];

            $subtotal += $lineSubtotal;
        }

        if ($lines === []) {
            return null;
        }

        $purchaseOrder = PurchaseOrder::create([
            'supplier_id' => $firstProduct->supplier_id,
            'po_number' => $this->numberGenerator->nextNumber(),
            'status' => PurchaseOrderStatus::Draft,
            'subtotal' => round($subtotal, 2),
            'tax' => 0,
            'total_amount' => round($subtotal, 2),
            'requested_by' => $requestedBy->id,
            'approved_by' => null,
            'expected_delivery_at' => $expectedDeliveryAt,
            'notes' => $notes,
        ]);

        foreach ($lines as $line) {
            $purchaseOrder->lines()->create($line);
        }

        $purchaseOrder->load(['supplier', 'requester', 'lines.product']);

        return $purchaseOrder;
    }

    private function resolveRecommendedQuantity(Product $product): int
    {
        $onHand = (int) ($product->stock?->on_hand_qty ?? 0);
        $fallbackQty = max(0, ((int) $product->reorder_threshold * 2) - $onHand);

        $snapshot = ForecastSnapshot::query()
            ->where('product_id', $product->id)
            ->orderByDesc('forecast_date')
            ->orderByDesc('generated_at')
            ->first();

        if (! $snapshot instanceof ForecastSnapshot) {
            return $fallbackQty;
        }

        return max($fallbackQty, (int) $snapshot->recommended_reorder_qty);
    }
}
