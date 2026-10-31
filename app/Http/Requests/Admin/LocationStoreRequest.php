<?php

namespace App\Http\Requests\Admin;

use App\Enums\FacilityGroup;
use App\Enums\LocationType;
use App\Models\Location;
use App\Rules\LocationTypeMatchesFacilityGroup;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Location::class) ?? false;
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
            'parent_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'code' => ['required', 'string', 'max:50', new UniqueIgnoringCase('locations', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'facility_group' => ['nullable', Rule::enum(FacilityGroup::class)],
            'location_type' => ['nullable', Rule::enum(LocationType::class), new LocationTypeMatchesFacilityGroup($this->input('facility_group'))],
            'building' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:100'],
            'is_bookable' => ['sometimes', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
