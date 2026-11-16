<?php

namespace App\Http\Resources;

use App\Models\Requisition;
use App\Models\RequisitionLine;
use Illuminate\Http\Request;

/** @mixin Requisition */
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
            'totals' => $this->lineTotals(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'deleted_by' => $this->relationLoaded('deletedBy') && $this->deletedBy
                ? (new UserResource($this->deletedBy))->resolve($request)
                : null,
            'deletion_reason' => $this->deletion_reason,
            'can_delete' => $request->user()?->can('delete', $this->resource) ?? false,
        ];
    }

    /**
     * @return array{line_count: int, qty_requested: int, qty_issued: int}|null
     */
    private function lineTotals(): ?array
    {
        if (! $this->relationLoaded('lines')) {
            return null;
        }

        return [
            'line_count' => $this->lines->count(),
            'qty_requested' => (int) $this->lines->sum('qty_requested'),
            'qty_issued' => (int) $this->lines->sum('qty_issued'),
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
                'variant' => $line->relationLoaded('product') ? $line->product?->variant : null,
                'unit' => $line->relationLoaded('product') && $line->product?->relationLoaded('unitOfMeasurement')
                    ? $line->product->unitOfMeasurement?->abbreviation
                    : null,
                'type' => $line->relationLoaded('product') ? $line->product?->type?->value : null,
                'qty_requested' => (int) $line->qty_requested,
                'qty_issued' => (int) $line->qty_issued,
                'qty_remaining' => $line->remainingQuantity(),
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
