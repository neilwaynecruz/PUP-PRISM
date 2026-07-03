<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use App\Services\NotificationPreferenceDefaults;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $allowedEventTypes = $user instanceof User
            ? app(NotificationPreferenceDefaults::class)->eventTypesForUser($user)
            : [];

        return [
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.event_type' => ['required', 'string', Rule::in($allowedEventTypes)],
            'preferences.*.mail_enabled' => ['required', 'boolean'],
            'preferences.*.database_enabled' => ['required', 'boolean'],
            'preferences.*.broadcast_enabled' => ['required', 'boolean'],
            'preferences.*.digest_frequency' => ['required', Rule::in(['instant', 'daily'])],
        ];
    }
}
