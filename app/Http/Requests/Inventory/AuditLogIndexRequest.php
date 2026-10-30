<?php

namespace App\Http\Requests\Inventory;

use App\Models\AuditLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AuditLog::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'action' => ['nullable', 'string', 'max:64'],
            'model_type' => ['nullable', 'string', 'max:64'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'oldest'])],
        ];
    }

    /**
     * @return array{search: string, action: string, model_type: string, user_id: int, date_from: string, date_to: string, sort: string}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'action' => trim((string) ($validated['action'] ?? '')),
            'model_type' => trim((string) ($validated['model_type'] ?? '')),
            'user_id' => (int) ($validated['user_id'] ?? 0),
            'date_from' => (string) ($validated['date_from'] ?? ''),
            'date_to' => (string) ($validated['date_to'] ?? ''),
            'sort' => (string) ($validated['sort'] ?? 'newest'),
        ];
    }
}
