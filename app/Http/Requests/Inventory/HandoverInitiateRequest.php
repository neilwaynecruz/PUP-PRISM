<?php

namespace App\Http\Requests\Inventory;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HandoverInitiateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Admin', 'Property Custodian']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'asset_tag_code' => ['required', 'string', 'max:64', 'exists:assets,tag_code'],
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $recipientId = $this->integer('to_user_id');

                if ($recipientId === 0) {
                    return;
                }

                $recipient = User::query()->select(['id', 'position_id'])->find($recipientId);

                if ($recipient?->position_id === null) {
                    $validator->errors()->add('to_user_id', __('The selected recipient must be assigned to a position.'));
                }
            },
        ];
    }
}
