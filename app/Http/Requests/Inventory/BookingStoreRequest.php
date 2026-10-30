<?php

namespace App\Http\Requests\Inventory;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Throwable;

class BookingStoreRequest extends FormRequest
{
    /**
     * Allow a small grace period so a booking that starts "now" is not rejected
     * by the seconds it takes to fill in the form.
     */
    private const PAST_START_GRACE_MINUTES = 5;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'purpose' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asset_id.required' => __('Choose the asset you want to book.'),
            'end_at.after' => __('The end time must be later than the start time.'),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('start_at')) {
                    return;
                }

                try {
                    $startAt = $this->startAt();
                } catch (Throwable) {
                    return;
                }

                if ($startAt->lt(CarbonImmutable::now()->subMinutes(self::PAST_START_GRACE_MINUTES))) {
                    $validator->errors()->add('start_at', __('The start time has already passed. Choose a current or future time.'));
                }
            },
        ];
    }

    /**
     * Booking times normalised to the application timezone.
     *
     * Values without an offset (from datetime-local inputs) are read in the application
     * timezone; values with an explicit offset are converted, so storage is never shifted.
     */
    public function startAt(): CarbonImmutable
    {
        return $this->normalise((string) $this->input('start_at'));
    }

    public function endAt(): CarbonImmutable
    {
        return $this->normalise((string) $this->input('end_at'));
    }

    private function normalise(string $value): CarbonImmutable
    {
        $timezone = (string) config('app.timezone');

        return CarbonImmutable::parse($value, $timezone)->setTimezone($timezone);
    }
}
