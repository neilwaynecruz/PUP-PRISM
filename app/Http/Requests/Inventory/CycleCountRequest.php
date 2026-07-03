<?php

namespace App\Http\Requests\Inventory;

use App\Enums\StockMovementReasonCode;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CycleCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Admin') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'counted_qty' => ['required', 'integer', 'min:0'],
            'reason_code' => ['required', 'string', 'in:'.implode(',', StockMovementReasonCode::cycleCountValues())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $product = Product::query()->find($this->integer('product_id'));

                if ($product?->type?->value !== 'consumable') {
                    $validator->errors()->add('product_id', __('Only consumable products can be cycle-counted through this workflow.'));
                }
            },
        ];
    }
}
