<?php

namespace App\Http\Requests\Inventory;

use App\Models\Requisition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RequisitionIssueRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:2000'],
            'mark_as_backordered' => ['nullable', 'boolean'],
            'lines' => ['nullable', 'array'],
            'lines.*.id' => ['required_with:lines', 'integer', 'distinct'],
            'lines.*.qty_to_issue' => ['required_with:lines', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $requisition = $this->route('requisition');

                if (! $requisition instanceof Requisition) {
                    return;
                }

                $linePayloads = collect($this->input('lines', []));

                if (! $this->has('lines')) {
                    return;
                }

                $lineMap = $requisition->lines()->get()->keyBy('id');
                $hasIssueQuantity = false;

                foreach ($linePayloads as $index => $linePayload) {
                    $line = $lineMap->get((int) ($linePayload['id'] ?? 0));

                    if (! $line) {
                        $validator->errors()->add("lines.{$index}.id", __('The selected requisition line is invalid.'));

                        continue;
                    }

                    $qtyToIssue = (int) ($linePayload['qty_to_issue'] ?? 0);

                    if ($qtyToIssue > $line->remainingQuantity()) {
                        $validator->errors()->add(
                            "lines.{$index}.qty_to_issue",
                            __('The issue quantity may not exceed the remaining quantity for this line.'),
                        );
                    }

                    if ($qtyToIssue > 0) {
                        $hasIssueQuantity = true;
                    }
                }

                if (! $hasIssueQuantity && ! $this->boolean('mark_as_backordered')) {
                    $validator->errors()->add(
                        'lines',
                        __('Enter at least one issue quantity or mark the requisition as backordered.'),
                    );
                }
            },
        ];
    }

    /**
     * @return array<int, array{id: int, qty_to_issue: int}>
     */
    public function issueLines(): array
    {
        /** @var array<int, array{id: int, qty_to_issue: int}> $lines */
        $lines = collect($this->validated('lines', []))
            ->map(fn (array $line): array => [
                'id' => (int) $line['id'],
                'qty_to_issue' => (int) $line['qty_to_issue'],
            ])
            ->all();

        return $lines;
    }

    public function shouldMarkAsBackordered(): bool
    {
        return $this->boolean('mark_as_backordered');
    }
}
