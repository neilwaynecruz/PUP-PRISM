<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkTrashActionRequest extends FormRequest
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
        $itemRules = ['integer', 'distinct', 'min:1'];
        $table = $this->resolveTrashTable();

        if ($table !== null) {
            $itemRules[] = Rule::exists($table, 'id');
        }

        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => $itemRules,
        ];
    }

    /**
     * Return deduplicated integer IDs from the validated payload.
     *
     * @return list<int>
     */
    public function ids(): array
    {
        /** @var array<int, int|string> $rawIds */
        $rawIds = $this->validated('ids', []);

        return array_values(array_unique(array_map('intval', $rawIds)));
    }

    private function resolveTrashTable(): ?string
    {
        $routeName = (string) ($this->route()?->getName() ?? '');

        return match (true) {
            str_contains($routeName, 'inventory.products.') => 'products',
            str_contains($routeName, 'inventory.requisitions.') => 'requisitions',
            str_contains($routeName, 'inventory.bookings.') => 'bookings',
            default => null,
        };
    }
}
