<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;

/** @mixin Product */
class ProductResource extends InertiaJsonResource
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
            'sku' => $this->sku,
            'name' => $this->name,
            'variant' => $this->variant,
            'brand' => $this->brand,
            'model' => $this->model,
            'color' => $this->color,
            'size' => $this->size,
            'specifications' => $this->specifications,
            'type' => $this->type->value,
            'is_active' => $this->is_active,
            'reorder_threshold' => $this->reorder_threshold,
            'category_id' => $this->category_id,
            'category' => $this->relationLoaded('category') ? $this->category?->name : null,
            'origin_id' => $this->origin_id,
            'origin' => $this->relationLoaded('origin') ? $this->origin?->name : null,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->relationLoaded('supplier') ? $this->supplier?->name : null,
            'unit_of_measurement_id' => $this->unit_of_measurement_id,
            'unit_of_measurement' => $this->relationLoaded('unitOfMeasurement') && $this->unitOfMeasurement
                ? [
                    'id' => (int) $this->unitOfMeasurement->id,
                    'name' => $this->unitOfMeasurement->name,
                    'abbreviation' => $this->unitOfMeasurement->abbreviation,
                ]
                : null,
            'lead_time_days' => $this->lead_time_days,
            'unit_price' => $this->unit_price !== null ? round((float) $this->unit_price, 2) : null,
            'on_hand_qty' => $this->relationLoaded('stock') ? $this->stock?->on_hand_qty : null,
            'assets_count' => isset($this->assets_count) ? (int) $this->assets_count : null,
            'is_bookable' => $this->is_bookable,
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'deleted_by' => $this->relationLoaded('deletedBy') && $this->deletedBy
                ? (new UserResource($this->deletedBy))->resolve($request)
                : null,
            'deletion_reason' => $this->deletion_reason,
            'can_delete' => $request->user()?->can('delete', $this->resource) ?? false,
        ];
    }
}
