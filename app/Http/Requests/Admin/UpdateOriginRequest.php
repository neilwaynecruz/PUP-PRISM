<?php

namespace App\Http\Requests\Admin;

use App\Models\Origin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOriginRequest extends FormRequest
{
    public function authorize(): bool
    {
        $origin = $this->route('origin');

        return $origin instanceof Origin
            && ($this->user()?->can('update', $origin) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Origin $origin */
        $origin = $this->route('origin');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('origins', 'name')->ignore($origin->id)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
