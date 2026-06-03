<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StockMovementFilterRequest extends FormRequest
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
            'type' => ['nullable', 'string', 'in:receive,issue,transfer,condemn,return'],
            'search' => ['nullable', 'string', 'max:200'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'performed_by' => ['nullable', 'integer', 'exists:users,id'],
            'sort' => ['nullable', 'string', 'in:performed_at,movement_type,product_id,performed_by'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
