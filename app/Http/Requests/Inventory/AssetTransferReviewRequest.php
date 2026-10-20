<?php

namespace App\Http\Requests\Inventory;

use App\Models\AssetTransfer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssetTransferReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $transfer = $this->route('assetTransfer');

        return $transfer instanceof AssetTransfer
            && ($this->user()?->can('approve', $transfer) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
