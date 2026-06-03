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
            'name' => $this->relationLoaded('product') ? $this->product?->name : null,
            'position' => $this->positionSummary(),
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
