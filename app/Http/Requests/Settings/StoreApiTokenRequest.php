<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Admin', 'Supply Head']) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(['read', 'write'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $abilities = array_values(array_filter(
            (array) $this->input('abilities', []),
            static fn (mixed $ability): bool => is_string($ability) && $ability !== '',
        ));

        $this->merge([
            'abilities' => $abilities,
        ]);
    }
}
