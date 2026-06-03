<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

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
            'type' => $this->type?->value,
            'is_active' => $this->is_active,
            'reorder_threshold' => $this->reorder_threshold,
            'category_id' => $this->category_id,
            'category' => $this->relationLoaded('category') ? $this->category?->name : null,
            'origin_id' => $this->origin_id,
            'origin' => $this->relationLoaded('origin') ? $this->origin?->name : null,
            'on_hand_qty' => $this->relationLoaded('stock') ? $this->stock?->on_hand_qty : null,
            'assets_count' => isset($this->assets_count) ? (int) $this->assets_count : null,
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'deleted_by' => $this->relationLoaded('deletedBy') && $this->deletedBy
                ? (new UserResource($this->deletedBy))->resolve($request)
                : null,
            'deletion_reason' => $this->deletion_reason,
        ];
    }
}
