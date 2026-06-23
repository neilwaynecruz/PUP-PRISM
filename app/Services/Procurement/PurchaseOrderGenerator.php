<?php

namespace App\Services\Procurement;

use App\Enums\ProductType;
use App\Enums\PurchaseOrderStatus;
use App\Models\ForecastSnapshot;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseOrderGenerator
{
    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function generateFromAlerts(User $requestedBy): Collection
    {
        $products = Product::query()
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->whereNotNull('supplier_id')
            ->whereHas('stock', fn ($query) => $query->whereColumn('on_hand_qty', '<=', 'products.reorder_threshold'))
            ->with([
                'stock:id,product_id,on_hand_qty',
                'supplier:id,name,lead_time_days,is_active',
            ])
            ->get()
            ->filter(fn (Product $product) => $product->supplier?->is_active)
            ->groupBy('supplier_id');

        $created = new Collection;

        foreach ($products as $supplierProducts) {
            $purchaseOrder = DB::transaction(fn (): ?PurchaseOrder => $this->createDraftPurchaseOrder(
                $requestedBy,
                new Collection($supplierProducts->all()),
            ));

            if ($purchaseOrder instanceof PurchaseOrder) {
                $created->push($purchaseOrder);
            }
        }

        return $created;
    }

    private function createDraftPurchaseOrder(User $requestedBy, Collection $products): ?PurchaseOrder
    {
        /** @var Product|null $firstProduct */
        $firstProduct = $products->first();

        if (! $firstProduct instanceof Product || $firstProduct->supplier_id === null) {
            return null;
        }

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
            'po_number' => $this->nextPoNumber(),
            'status' => PurchaseOrderStatus::Draft,
            'subtotal' => round($subtotal, 2),
            'tax' => 0,
            'total_amount' => round($subtotal, 2),
            'requested_by' => $requestedBy->id,
            'approved_by' => null,
            'expected_delivery_at' => $expectedDeliveryAt,
            'notes' => 'Auto-generated from low-stock and forecast signals.',
        ]);

        foreach ($lines as $line) {
            $purchaseOrder->lines()->create($line);
        }

        return $purchaseOrder->fresh(['supplier', 'requester', 'lines.product']);
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

    private function nextPoNumber(): string
    {
        $prefix = 'PO-'.CarbonImmutable::now()->format('Ymd').'-';
        $lastPoNumber = PurchaseOrder::withTrashed()
            ->where('po_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('po_number');

        $sequence = $lastPoNumber !== null
            ? ((int) substr($lastPoNumber, -4)) + 1
            : 1;

        return sprintf('%s%04d', $prefix, $sequence);
    }
}
