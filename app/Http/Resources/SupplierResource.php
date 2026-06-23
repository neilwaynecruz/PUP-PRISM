<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class SupplierResource extends InertiaJsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'website' => $this->website,
            'payment_terms' => $this->payment_terms,
            'lead_time_days' => $this->lead_time_days,
            'is_active' => (bool) $this->is_active,
            'notes' => $this->notes,
            'products_count' => isset($this->products_count) ? (int) $this->products_count : null,
            'purchase_orders_count' => isset($this->purchase_orders_count) ? (int) $this->purchase_orders_count : null,
            'avg_lead_time_days' => isset($this->avg_lead_time_days) && $this->avg_lead_time_days !== null
                ? round((float) $this->avg_lead_time_days, 2)
                : null,
            'can_delete' => $request->user()?->can('delete', $this->resource) ?? false,
        ];
    }
}
