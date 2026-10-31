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
            'parent_location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->whereNot('id', $this->route('location')?->id),
            ],
            'code' => ['required', 'string', 'max:50', (new UniqueIgnoringCase('locations', 'code'))->ignore($this->route('location'))],
            'name' => ['required', 'string', 'max:255'],
            'facility_group' => ['nullable', Rule::enum(FacilityGroup::class)],
            'location_type' => ['nullable', Rule::enum(LocationType::class), new LocationTypeMatchesFacilityGroup($this->input('facility_group'))],
            'building' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:100'],
            'is_bookable' => ['sometimes', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $location = $this->route('location');
                $parentId = $this->integer('parent_location_id');

                if (! $location instanceof Location || $parentId === 0) {
                    return;
                }

                $candidate = Location::query()->find($parentId);
                $visitedLocationIds = [];

                while ($candidate instanceof Location) {
                    if (in_array($candidate->id, $visitedLocationIds, true)) {
                        $validator->errors()->add('parent_location_id', __('The selected parent location already belongs to an invalid location cycle.'));

                        return;
                    }

                    $visitedLocationIds[] = $candidate->id;

                    if ($candidate->is($location)) {
                        $validator->errors()->add('parent_location_id', __('A location cannot be nested below itself or one of its descendants.'));

                        return;
                    }

                    $candidate = $candidate->parent_location_id === null
                        ? null
                        : Location::query()->find($candidate->parent_location_id);
                }
            },
        ];
    }
}
