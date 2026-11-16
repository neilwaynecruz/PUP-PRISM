<?php

namespace App\Http\Resources;

use App\Models\Asset;
use Illuminate\Http\Request;

/** @mixin Asset */
class AssetResource extends InertiaJsonResource
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
            'tag_code' => $this->tag_code,
            'serial_number' => $this->serial_number,
            'status' => $this->status->value,
            'lifecycle_status' => $this->lifecycle_status->value,
            'condition_status' => $this->condition_status->value,
            'acquisition_date' => $this->acquisition_date?->toDateString(),
            'acquisition_cost' => $this->acquisition_cost !== null ? round((float) $this->acquisition_cost, 2) : null,
            'warranty_expires_at' => $this->warranty_expires_at?->toDateString(),
            'installed_at' => $this->installed_at?->toDateString(),
            'maintenance_interval_days' => $this->maintenance_interval_days,
            'name' => $this->relationLoaded('product') ? $this->product?->name : null,
            'position' => $this->positionSummary(),
            'location' => $this->relationLoaded('currentLocation') && $this->currentLocation
                ? [
                    'code' => $this->currentLocation->code,
                    'name' => $this->currentLocation->name,
                ]
                : null,
        ];
    }

    /**
     * @return array{title: string, department: string|null}|null
     */
    private function positionSummary(): ?array
    {
        if (! $this->relationLoaded('position') || ! $this->position) {
            return null;
        }

        return [
            'title' => $this->position->title,
            'department' => $this->position->relationLoaded('department')
                ? $this->position->department?->name
                : null,
        ];
    }
}
