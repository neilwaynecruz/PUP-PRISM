<?php

namespace App\Rules;

use App\Enums\FacilityGroup;
use App\Enums\LocationType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Ensures a room or facility type is only classified under a facility group it belongs to,
 * for example a Gymnasium under Sports or a Classroom under Academic.
 */
class LocationTypeMatchesFacilityGroup implements ValidationRule
{
    public function __construct(private readonly mixed $facilityGroup) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $type = is_string($value) ? LocationType::tryFrom($value) : null;
        $group = is_string($this->facilityGroup) ? FacilityGroup::tryFrom($this->facilityGroup) : null;

        // Invalid enum values are reported by the enum rules; an unclassified group imposes no constraint.
        if ($type === null || $group === null) {
            return;
        }

        if (! $type->belongsToFacilityGroup($group)) {
            $fail(__('A :type cannot be classified under the :group facility group.', [
                'type' => $type->label(),
                'group' => $group->label(),
            ]));
        }
    }
}
