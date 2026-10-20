<?php

namespace App\Http\Requests\Inventory;

use App\Models\AssetLifecycleEvent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssetLifecycleReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $event = $this->route('assetLifecycleEvent');

        return $event instanceof AssetLifecycleEvent
            && ($this->user()?->can('approve', $event) ?? false);
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
