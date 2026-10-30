<?php

namespace App\Http\Requests\Admin;

use App\Models\Origin;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;

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
            'name' => ['required', 'string', 'max:255', (new UniqueIgnoringCase('origins', 'name'))->ignore($origin->id)],
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
