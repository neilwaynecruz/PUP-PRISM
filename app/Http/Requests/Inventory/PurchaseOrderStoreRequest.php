<?php

namespace App\Http\Requests\Inventory;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PurchaseOrderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchaseOrder::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'expected_delivery_at' => ['nullable', 'date'],
            'tax' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'lines.*.qty_ordered' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $supplierId = $this->integer('supplier_id');

                if ($supplierId < 1) {
                    return;
                }

                /** @var array<int, array<string, mixed>> $lines */
                $lines = $this->input('lines', []);
                $productIds = collect($lines)
                    ->pluck('product_id')
                    ->filter()
                    ->map(fn (mixed $id) => (int) $id)
                    ->values();

                if ($productIds->isEmpty()) {
                    return;
                }

                $products = Product::query()
                    ->whereIn('id', $productIds)
                    ->get(['id', 'supplier_id', 'is_active'])
                    ->keyBy('id');

                foreach ($productIds as $index => $productId) {
                    /** @var Product|null $product */
                    $product = $products->get($productId);

                    if (! $product) {
                        continue;
                    }

                    if (! $product->is_active) {
                        $validator->errors()->add("lines.{$index}.product_id", __('Only active products can be ordered.'));
                    }

                    if ($product->supplier_id !== null && (int) $product->supplier_id !== $supplierId) {
                        $supplierName = Supplier::query()->whereKey($product->supplier_id)->value('name') ?? __('another supplier');

                        $validator->errors()->add(
                            "lines.{$index}.product_id",
                            __('This product is already assigned to :supplier.', ['supplier' => $supplierName]),
                        );
                    }
                }
            },
        ];
    }
}
