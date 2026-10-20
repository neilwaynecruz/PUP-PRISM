<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryCountSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InventoryCountLineRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $session = $this->route('inventoryCountSession');

        return $session instanceof InventoryCountSession
            && ($this->user()?->can('update', $session) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'counted_qty' => ['required', 'integer', 'min:0'],
            'count_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
