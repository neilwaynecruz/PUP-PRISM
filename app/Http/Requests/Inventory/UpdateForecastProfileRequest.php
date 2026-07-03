<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateForecastProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Admin', 'Supply Head']) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', 'string', Rule::in(['moving_average', 'exponential_smoothing', 'seasonal'])],
            'lookback_days' => ['required', 'integer', 'min:14', 'max:365'],
            'forecast_horizon_days' => ['required', 'integer', 'min:7', 'max:90'],
            'lead_time_days' => ['required', 'integer', 'min:1', 'max:90'],
            'safety_stock_days' => ['required', 'integer', 'min:0', 'max:90'],
        ];
    }
}
