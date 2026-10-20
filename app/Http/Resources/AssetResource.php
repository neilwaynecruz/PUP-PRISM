<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

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
            'status' => $this->status?->value,
            'lifecycle_status' => $this->lifecycle_status?->value,
            'condition_status' => $this->condition_status?->value,
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
