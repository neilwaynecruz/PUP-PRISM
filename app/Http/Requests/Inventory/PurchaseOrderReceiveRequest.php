<?php

namespace App\Http\Requests\Inventory;

use App\Enums\ProductType;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseOrderReceiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = $this->route('purchaseOrder');

        return $this->user()?->can('receive', $purchaseOrder) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.purchase_order_line_id' => ['required', 'integer', 'distinct', Rule::exists('purchase_order_lines', 'id')],
            'lines.*.qty_received' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'lines.*.reference_no' => ['nullable', 'string', 'max:64'],
            'lines.*.received_at' => ['nullable', 'date'],
            'lines.*.expires_at' => ['nullable', 'date'],
            'lines.*.tag_codes' => ['nullable', 'array', 'min:1'],
            'lines.*.tag_codes.*' => ['string', 'max:64', 'distinct'],
            'lines.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function validatedLines(): array
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = $this->route('purchaseOrder');
        /** @var array<int, array<string, mixed>> $lines */
        $lines = $this->validated('lines');

        $lineModels = PurchaseOrderLine::query()
            ->with('product:id,sku,type')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->whereIn('id', collect($lines)->pluck('purchase_order_line_id'))
            ->get()
            ->keyBy('id');

        $validatedLines = [];

        foreach ($lines as $index => $line) {
            /** @var PurchaseOrderLine|null $purchaseOrderLine */
            $purchaseOrderLine = $lineModels->get((int) $line['purchase_order_line_id']);

            if (! $purchaseOrderLine) {
                throw ValidationException::withMessages([
                    "lines.{$index}.purchase_order_line_id" => __('This line does not belong to the selected purchase order.'),
                ]);
            }

            $remainingQty = $purchaseOrderLine->remainingQty();

            if ($remainingQty < 1) {
                throw ValidationException::withMessages([
                    "lines.{$index}.qty_received" => __('This line has already been fully received.'),
                ]);
            }

            if ($purchaseOrderLine->product?->type === ProductType::Consumable) {
                $qtyReceived = isset($line['qty_received']) && $line['qty_received'] !== null
                    ? (int) $line['qty_received']
                    : null;

                if ($qtyReceived === null || $qtyReceived < 1) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.qty_received" => __('Quantity is required for consumable line items.'),
                    ]);
                }

                if ($qtyReceived > $remainingQty) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.qty_received" => __('The received quantity exceeds the remaining order quantity.'),
                    ]);
                }

                $validatedLines[] = [
                    'purchase_order_line_id' => $purchaseOrderLine->id,
                    'qty_received' => $qtyReceived,
                    'reference_no' => $line['reference_no'] ?? null,
                    'received_at' => isset($line['received_at']) ? CarbonImmutable::parse((string) $line['received_at']) : null,
                    'expires_at' => isset($line['expires_at']) ? CarbonImmutable::parse((string) $line['expires_at']) : null,
                    'tag_codes' => null,
                    'notes' => $line['notes'] ?? null,
                ];

                continue;
            }

            /** @var array<int, string>|null $tagCodes */
            $tagCodes = $line['tag_codes'] ?? null;

            if (empty($tagCodes)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.tag_codes" => __('At least one asset tag code is required for asset line items.'),
                ]);
            }

            if (count($tagCodes) > $remainingQty) {
                throw ValidationException::withMessages([
                    "lines.{$index}.tag_codes" => __('The number of asset tags exceeds the remaining order quantity.'),
                ]);
            }

            if (
                isset($line['qty_received'])
                && $line['qty_received'] !== null
                && (int) $line['qty_received'] !== count($tagCodes)
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.qty_received" => __('Asset receipt quantity must match the number of tag codes.'),
                ]);
            }

            $validatedLines[] = [
                'purchase_order_line_id' => $purchaseOrderLine->id,
                'qty_received' => count($tagCodes),
                'reference_no' => $line['reference_no'] ?? null,
                'received_at' => isset($line['received_at']) ? CarbonImmutable::parse((string) $line['received_at']) : null,
                'expires_at' => null,
                'tag_codes' => $tagCodes,
                'notes' => $line['notes'] ?? null,
            ];
        }

        return $validatedLines;
    }
}
