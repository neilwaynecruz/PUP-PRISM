<?php

namespace App\Http\Requests\Inventory;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class ProductLabelRequest extends FormRequest
{
    public const MAX_PRODUCTS = 500;

    public const MAX_LABELS = 500;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Product::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_ids' => ['nullable', 'array', 'min:1', 'max:'.self::MAX_PRODUCTS],
            'product_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],
            'selection_scope' => ['required_without:product_ids', 'nullable', Rule::in(['filtered'])],
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', new Enum(ProductType::class)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'active' => ['nullable', 'boolean'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:50'],
            'paper_size' => ['nullable', Rule::in(['a4', 'letter'])],
            'grid_preset' => ['nullable', Rule::in(['compact_30', 'standard_24', 'medium_18', 'large_12', 'single_1'])],
            'show_sku' => ['nullable', 'boolean'],
            'show_name' => ['nullable', 'boolean'],
            'show_category' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $productCount = count($this->input('product_ids', []));
                $copies = $this->integer('copies', 1);

                if ($productCount > 0 && $productCount * $copies > self::MAX_LABELS) {
                    $validator->errors()->add(
                        'copies',
                        'This request would create more than 500 labels. Reduce the selected products or copies.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $productIds = $this->input('product_ids');

        if (is_string($productIds)) {
            $this->merge([
                'product_ids' => $productIds === '' ? [] : explode(',', $productIds),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'selection_scope.required_without' => 'Select products or explicitly choose all products matching the current filters.',
            'product_ids.max' => 'Select no more than 500 products at a time.',
            'product_ids.*.exists' => 'One or more selected products are no longer available.',
        ];
    }
}
