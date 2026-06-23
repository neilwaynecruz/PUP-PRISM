<?php

namespace App\Http\Requests\Inventory;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReceiveStockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Admin', 'Supply Head']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:64', 'exists:products,sku'],
            'reference_no' => ['nullable', 'string', 'max:64'],
            'received_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'qty' => ['nullable', 'integer', 'min:1'],
            'tag_codes' => ['nullable', 'array', 'min:1'],
            'tag_codes.*' => ['string', 'max:64', 'distinct'],
            'purchase_order_id' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')],
            'purchase_order_line_id' => ['nullable', 'integer', Rule::exists('purchase_order_lines', 'id')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $sku = $this->string('sku')->toString();
                $product = Product::query()->where('sku', $sku)->first();

                if (! $product) {
                    return;
                }

                if ($product->type === ProductType::Consumable) {
                    if (! $this->filled('qty')) {
                        $validator->errors()->add('qty', __('Quantity is required for consumables.'));
                    }
                }

                if ($product->type === ProductType::Asset && ! $this->filled('tag_codes')) {
                    $validator->errors()->add('tag_codes', __('At least one tag code is required for assets.'));
                }

                $purchaseOrderId = $this->integer('purchase_order_id');
                $purchaseOrderLineId = $this->integer('purchase_order_line_id');

                if (($purchaseOrderId > 0 || $purchaseOrderLineId > 0) && ! ($purchaseOrderId > 0 && $purchaseOrderLineId > 0)) {
                    $validator->errors()->add('purchase_order_line_id', __('Both purchase order and line are required when receiving against a purchase order.'));

                    return;
                }

                if ($purchaseOrderId < 1 || $purchaseOrderLineId < 1) {
                    return;
                }

                /** @var PurchaseOrderLine|null $purchaseOrderLine */
                $purchaseOrderLine = PurchaseOrderLine::query()
                    ->with(['purchaseOrder', 'product:id,sku,type'])
                    ->find($purchaseOrderLineId);

                /** @var PurchaseOrder|null $purchaseOrder */
                $purchaseOrder = PurchaseOrder::query()->find($purchaseOrderId);

                if (! $purchaseOrderLine || ! $purchaseOrder || $purchaseOrderLine->purchase_order_id !== $purchaseOrder->id) {
                    $validator->errors()->add('purchase_order_line_id', __('The selected purchase order line is invalid.'));

                    return;
                }

                if ((int) $purchaseOrderLine->product_id !== (int) $product->id) {
                    $validator->errors()->add('sku', __('The selected SKU does not match the purchase order line.'));
                }

                if (! $purchaseOrder->canReceive()) {
                    $validator->errors()->add('purchase_order_id', __('This purchase order can no longer receive items.'));
                }

                $receivedQty = $product->type === ProductType::Consumable
                    ? (int) $this->input('qty', 0)
                    : count((array) $this->input('tag_codes', []));

                if ($receivedQty > $purchaseOrderLine->remainingQty()) {
                    $validator->errors()->add('qty', __('The receipt exceeds the remaining quantity for this purchase order line.'));
                }
            },
        ];
    }
}
