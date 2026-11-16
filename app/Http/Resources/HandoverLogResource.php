<?php

namespace App\Http\Resources;

use App\Models\HandoverLog;
use Illuminate\Http\Request;

/**
 * @mixin HandoverLog
 *
 * @property HandoverLog $resource
 */
class HandoverLogResource extends InertiaJsonResource
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
            'status' => $this->resource->status(),
            'tag_code' => $this->relationLoaded('asset') ? $this->asset?->tag_code : null,
            'asset_name' => $this->assetName(),
            'to' => $this->relationLoaded('toUser') && $this->toUser
                ? (new UserResource($this->toUser))->resolve($request)
                : null,
            'from_user' => $this->relationLoaded('fromUser') && $this->fromUser
                ? (new UserResource($this->fromUser))->resolve($request)
                : null,
            'to_user' => $this->relationLoaded('toUser') && $this->toUser
                ? (new UserResource($this->toUser))->resolve($request)
                : null,
            'initiated_by' => $this->relationLoaded('initiatedBy') ? $this->initiatedBy?->name : null,
            'from_position' => $this->positionSummary('fromPosition'),
            'to_position' => $this->positionSummary('toPosition'),
            'initiated_at' => $this->initiated_at->toIso8601String(),
            'last_sent_at' => $this->last_sent_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => $this->relationLoaded('cancelledBy') ? $this->cancelledBy?->name : null,
            'cancellation_reason' => $this->cancellation_reason,
            'notes' => $this->notes,
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
     * @return array{title: string, code: string, department: string|null}|null
     */
    private function positionSummary(string $relation): ?array
    {
        if (! $this->relationLoaded($relation) || ! $this->{$relation}) {
            return null;
        }

        return [
            'title' => $this->{$relation}->title,
            'code' => $this->{$relation}->code,
            'department' => $this->{$relation}->relationLoaded('department')
                ? $this->{$relation}->department?->name
                : null,
        ];
    }
}
