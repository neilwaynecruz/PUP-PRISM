<?php

namespace App\Http\Requests\Api;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $rawKey = $this->input('idempotency_key');

        if (! is_string($rawKey) || trim($rawKey) === '') {
            $headerKey = $this->header('Idempotency-Key');

            if (is_string($headerKey) && trim($headerKey) !== '') {
                $this->merge([
                    'idempotency_key' => trim($headerKey),
                ]);
            }

            return;
        }

        $this->merge([
            'idempotency_key' => trim($rawKey),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string', 'min:8', 'max:128', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')
                    ->where('type', ProductType::Consumable->value)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'lines.*.qty_requested' => ['required', 'integer', 'min:1', 'max:99999'],
        ];
    }
}
