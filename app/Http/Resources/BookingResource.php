<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class BookingResource extends InertiaJsonResource
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
            'asset_id' => (int) $this->asset_id,
            'asset_label' => $this->relationLoaded('asset') ? $this->asset?->tag_code : null,
            'title' => ($this->assetName() ?? 'Asset').' - '.$this->status->value,
            'start' => $this->start_at?->toIso8601String(),
            'end' => $this->end_at?->toIso8601String(),
            'status' => $this->status->value,
            'requester_id' => (int) $this->requester_id,
            'requester' => $this->relationLoaded('requester') && $this->requester
                ? (new UserResource($this->requester))->resolve($request)
                : null,
            'requester_position' => $this->positionSummary('requesterPosition'),
            'approver' => $this->relationLoaded('approver') && $this->approver
                ? (new UserResource($this->approver))->resolve($request)
                : null,
            'requested_ip_address' => $this->requested_ip_address,
            'approved_ip_address' => $this->approved_ip_address,
        ];
    }

    private function assetName(): ?string
    {
        if (! $this->relationLoaded('asset') || ! $this->asset) {
            return null;
        }

        return $this->asset->relationLoaded('product')
            ? $this->asset->product?->name
            : null;
    }

    /**
     * @return array{title: string, department: string|null}|null
     */
    private function positionSummary(string $relation): ?array
    {
        if (! $this->relationLoaded($relation) || ! $this->{$relation}) {
            return null;
        }

        return [
            'title' => $this->{$relation}->title,
            'department' => $this->{$relation}->relationLoaded('department')
                ? $this->{$relation}->department?->name
                : null,
        ];
    }
}
