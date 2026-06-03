<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class StockMovementResource extends InertiaJsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'movement_type' => $this->movement_type,
            'qty_delta' => $this->qty_delta,
            'performed_at' => $this->performed_at?->toIso8601String(),
            'ip_address' => $this->ip_address,
            'notes' => $this->notes,
            'product' => $this->relationLoaded('product') && $this->product ? [
                'id' => (int) $this->product->id,
                'sku' => $this->product->sku,
                'name' => $this->product->name,
            ] : null,
            'stock_lot' => $this->relationLoaded('stockLot') && $this->stockLot ? [
                'id' => (int) $this->stockLot->id,
                'reference_no' => $this->stockLot->reference_no,
                'received_at' => $this->stockLot->received_at?->toIso8601String(),
                'expires_at' => $this->stockLot->expires_at?->toDateString(),
            ] : null,
            'asset' => $this->relationLoaded('asset') && $this->asset ? [
                'id' => (int) $this->asset->id,
                'tag_code' => $this->asset->tag_code,
                'status' => $this->asset->status?->value,
            ] : null,
            'performed_by' => $this->relationLoaded('performedBy') && $this->performedBy
                ? (new UserResource($this->performedBy))->resolve($request)
                : null,
            'accountable_position' => $this->positionSummary(),
        ];
    }

    /**
     * @return array{title: string, code: string, department: string|null}|null
     */
    private function positionSummary(): ?array
    {
        if (! $this->relationLoaded('accountablePosition') || ! $this->accountablePosition) {
            return null;
        }

        return [
            'title' => $this->accountablePosition->title,
            'code' => $this->accountablePosition->code,
            'department' => $this->accountablePosition->relationLoaded('department')
                ? $this->accountablePosition->department?->name
                : null,
        ];
    }
}
