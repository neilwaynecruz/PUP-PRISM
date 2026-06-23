<?php

namespace App\Http\Requests\Inventory;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = $this->route('purchaseOrder');

        return $this->user()?->can('cancel', $purchaseOrder) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
