<?php

namespace App\Http\Requests\Admin;

use App\Models\Origin;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;

class StoreOriginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Origin::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new UniqueIgnoringCase('origins', 'name')],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
