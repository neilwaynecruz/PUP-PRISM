<?php

namespace App\Http\Resources;

use App\Models\PurchaseOrderLine;
use Illuminate\Http\Request;

class PurchaseOrderResource extends InertiaJsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'supplier_id' => (int) $this->supplier_id,
            'po_number' => $this->po_number,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'subtotal' => round((float) $this->subtotal, 2),
            'tax' => round((float) $this->tax, 2),
            'total_amount' => round((float) $this->total_amount, 2),
            'expected_delivery_at' => $this->expected_delivery_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'notes' => $this->notes,
            'supplier' => $this->relationLoaded('supplier') && $this->supplier
                ? (new SupplierResource($this->supplier))->resolve($request)
                : null,
            'requester' => $this->relationLoaded('requester') && $this->requester
                ? (new UserResource($this->requester))->resolve($request)
                : null,
            'approver' => $this->relationLoaded('approver') && $this->approver
                ? (new UserResource($this->approver))->resolve($request)
                : null,
            'progress_pct' => method_exists($this->resource, 'progressPercent')
                ? $this->resource->progressPercent()
                : 0,
            'line_count' => isset($this->lines_count) ? (int) $this->lines_count : null,
            'lines' => $this->relationLoaded('lines')
                ? $this->lines->map(fn (PurchaseOrderLine $line): array => [
                    'id' => (int) $line->id,
                    'product_id' => (int) $line->product_id,
                    'product' => $line->relationLoaded('product') && $line->product
                        ? [
                            'id' => (int) $line->product->id,
                            'sku' => $line->product->sku,
                            'name' => $line->product->name,
                            'type' => $line->product->type?->value,
                            'supplier_id' => $line->product->supplier_id,
                        ]
                        : null,
                    'qty_ordered' => (int) $line->qty_ordered,
                    'qty_received' => (int) $line->qty_received,
                    'qty_remaining' => $line->remainingQty(),
                    'unit_price' => round((float) $line->unit_price, 2),
                    'subtotal' => round((float) $line->subtotal, 2),
                    'progress_pct' => $line->qty_ordered > 0
                        ? round(min(100, ($line->qty_received / $line->qty_ordered) * 100), 2)
                        : 0,
                ])->values()->all()
                : [],
        ];
    }
}
