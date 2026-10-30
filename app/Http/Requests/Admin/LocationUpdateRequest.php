<?php

namespace App\Http\Requests\Admin;

use App\Models\Location;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LocationUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $location = $this->route('location');

        return $location instanceof Location && ($this->user()?->can('update', $location) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'code' => ['required', 'string', 'max:50', (new UniqueIgnoringCase('locations', 'code'))->ignore($this->route('location'))],
            'name' => ['required', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
