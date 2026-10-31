<?php

namespace App\Http\Requests\Inventory;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:64', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'origin_id' => ['nullable', 'integer', 'exists:origins,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'unit_of_measurement_id' => ['nullable', 'integer', 'exists:unit_of_measurements,id'],
            'variant' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:80'],
            'size' => ['nullable', 'string', 'max:120'],
            'specifications' => ['nullable', 'array'],
            'specifications.*' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ProductType::class)],
            'reorder_threshold' => ['nullable', 'integer', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['required', 'boolean'],
            'is_bookable' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => strtoupper(trim((string) $this->input('sku'))),
            'name' => Str::squish((string) $this->input('name')),
            'category_id' => $this->filled('category_id') ? (int) $this->input('category_id') : null,
            'origin_id' => $this->filled('origin_id') ? (int) $this->input('origin_id') : null,
            'supplier_id' => $this->filled('supplier_id') ? (int) $this->input('supplier_id') : null,
            'unit_of_measurement_id' => $this->filled('unit_of_measurement_id') ? (int) $this->input('unit_of_measurement_id') : null,
            'reorder_threshold' => $this->filled('reorder_threshold') ? max(0, (int) $this->input('reorder_threshold')) : 0,
            'lead_time_days' => $this->filled('lead_time_days') ? max(0, (int) $this->input('lead_time_days')) : null,
            'unit_price' => $this->filled('unit_price') ? round((float) $this->input('unit_price'), 2) : null,
            'variant' => $this->filled('variant') ? Str::squish((string) $this->input('variant')) : null,
            'brand' => $this->filled('brand') ? Str::squish((string) $this->input('brand')) : null,
            'model' => $this->filled('model') ? Str::squish((string) $this->input('model')) : null,
            'color' => $this->filled('color') ? Str::squish((string) $this->input('color')) : null,
            'size' => $this->filled('size') ? Str::squish((string) $this->input('size')) : null,
            'specifications' => is_array($this->input('specifications')) ? $this->input('specifications') : null,
            'is_active' => $this->boolean('is_active', true),
            'is_bookable' => $this->boolean('is_bookable'),
        ]);
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $duplicateExists = Product::query()
                    ->whereRaw('LOWER(name) = ?', [Str::lower((string) $this->input('name'))])
                    ->where('category_id', $this->input('category_id'))
                    ->where('unit_of_measurement_id', $this->input('unit_of_measurement_id'))
                    ->whereRaw("LOWER(COALESCE(variant, '')) = ?", [Str::lower((string) $this->input('variant'))])
                    ->exists();

                if ($duplicateExists) {
                    $validator->errors()->add('name', __('An equivalent product with the same category, unit, and variant already exists.'));
                }
            },
        ];
    }
}
