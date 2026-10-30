<?php

namespace App\Http\Requests\Inventory;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asset_tag_code.exists' => __('No asset matches this tag code. Check the label or search by asset name.'),
            'to_user_id.required' => __('Choose who will receive this asset.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('asset_tag_code')) {
            $this->merge(['asset_tag_code' => trim((string) $this->input('asset_tag_code'))]);
        }
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('to_user_id')) {
                    return;
                }

                $recipient = User::query()
                    ->with('position:id,is_active')
                    ->select(['id', 'position_id', 'is_active'])
                    ->find($this->integer('to_user_id'));

                if ($recipient === null) {
                    return;
                }

                if (! $recipient->is_active) {
                    $validator->errors()->add('to_user_id', __('The selected recipient account is inactive.'));

                    return;
                }

                if ($recipient->position_id === null) {
                    $validator->errors()->add('to_user_id', __('The selected recipient must be assigned to a position.'));

                    return;
                }

                if ($recipient->position !== null && ! $recipient->position->is_active) {
                    $validator->errors()->add('to_user_id', __('The recipient\'s position is inactive. Update their position before handing over assets.'));

                    return;
                }

                if ($validator->errors()->has('asset_tag_code')) {
                    return;
                }

                $assetPositionId = Asset::query()
                    ->where('tag_code', (string) $this->input('asset_tag_code'))
                    ->value('position_id');

                if ($assetPositionId !== null && (int) $assetPositionId === (int) $recipient->position_id) {
                    $validator->errors()->add('to_user_id', __('This asset is already accountable to the recipient\'s position.'));
                }
            },
        ];
    }
}
