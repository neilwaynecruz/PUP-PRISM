<?php

namespace App\Http\Requests\Inventory;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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

                    return;
                }

                if (! $this->filled('tag_codes')) {
                    $validator->errors()->add('tag_codes', __('At least one tag code is required for assets.'));
                }
            },
        ];
    }
}
