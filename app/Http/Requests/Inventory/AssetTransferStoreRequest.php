<?php

namespace App\Http\Requests\Inventory;

use App\Models\AssetTransfer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssetTransferStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', AssetTransfer::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'to_location_id' => ['required', 'integer', 'exists:locations,id'],
            'to_position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'reason' => ['required', 'string', 'max:2000'],
            'transfer_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
