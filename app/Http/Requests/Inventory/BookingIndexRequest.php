<?php

namespace App\Http\Requests\Inventory;

use App\Enums\BookingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::enum(BookingStatus::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'requester_id' => ['nullable', 'integer', 'exists:users,id'],
            'asset_search' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function searchTerm(): string
    {
        return trim((string) $this->validated('search', ''));
    }

    public function assetSearchTerm(): string
    {
        return trim((string) $this->validated('asset_search', ''));
    }

    public function statusFilter(): ?string
    {
        $status = trim((string) $this->validated('status', ''));

        return $status === '' ? null : $status;
    }
}
