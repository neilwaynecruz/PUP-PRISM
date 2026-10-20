<?php

namespace App\Http\Requests\Inventory;

use App\Enums\AssetConditionStatus;
use App\Enums\AssetLifecycleEventType;
use App\Models\AssetLifecycleEvent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetLifecycleStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', AssetLifecycleEvent::class) ?? false;
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
            'event_type' => ['required', Rule::in([
                AssetLifecycleEventType::ConditionChange->value,
                AssetLifecycleEventType::Retirement->value,
                AssetLifecycleEventType::Disposal->value,
            ])],
            'condition_status' => ['nullable', Rule::enum(AssetConditionStatus::class)],
            'reason' => ['required', 'string', 'max:2000'],
            'method' => ['nullable', 'string', 'max:255'],
            'supporting_reference' => ['nullable', 'string', 'max:255'],
            'proposed_for' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if ($this->input('event_type') === AssetLifecycleEventType::ConditionChange->value
                    && ! $this->filled('condition_status')) {
                    $validator->errors()->add('condition_status', __('Choose the asset condition to record.'));
                }
            },
        ];
    }
}
