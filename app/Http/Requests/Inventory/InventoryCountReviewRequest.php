<?php

namespace App\Http\Requests\Inventory;

use App\Enums\InventoryCountReviewStatus;
use App\Models\InventoryCountSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryCountReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $session = $this->route('inventoryCountSession');

        return $session instanceof InventoryCountSession
            && ($this->user()?->can('review', $session) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'review_status' => ['required', Rule::enum(InventoryCountReviewStatus::class)],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
