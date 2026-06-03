<?php

namespace App\Http\Resources;

use App\Models\RequisitionLine;
use Illuminate\Http\Request;

class RequisitionResource extends InertiaJsonResource
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
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'notes' => $this->notes,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'requested_ip_address' => $this->requested_ip_address,
            'approved_ip_address' => $this->approved_ip_address,
            'issued_ip_address' => $this->issued_ip_address,
            'requester' => $this->relationLoaded('requester') && $this->requester
                ? (new UserResource($this->requester))->resolve($request)
                : null,
            'requester_position' => $this->positionSummary('requesterPosition'),
            'approver' => $this->relationLoaded('approver') && $this->approver
                ? (new UserResource($this->approver))->resolve($request)
                : null,
            'approver_position' => $this->positionSummary('approverPosition'),
            'issuer' => $this->relationLoaded('issuer') && $this->issuer
                ? (new UserResource($this->issuer))->resolve($request)
                : null,
            'issued_position' => $this->positionSummary('issuedPosition'),
            'lines' => $this->lineItems(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lineItems(): array
    {
        if (! $this->relationLoaded('lines')) {
            return [];
        }

        return $this->lines
            ->map(fn (RequisitionLine $line) => [
                'id' => (int) $line->id,
                'sku' => $line->relationLoaded('product') ? $line->product?->sku : null,
                'name' => $line->relationLoaded('product') ? $line->product?->name : null,
                'type' => $line->relationLoaded('product') ? $line->product?->type?->value : null,
                'qty_requested' => (int) $line->qty_requested,
                'qty_issued' => (int) $line->qty_issued,
            ])
            ->values()
            ->all();
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
