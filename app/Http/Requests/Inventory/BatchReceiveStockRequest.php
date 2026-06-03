<?php

namespace App\Http\Requests\Inventory;

use App\Enums\ProductType;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class BatchReceiveStockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Admin', 'Supply Head']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.sku' => ['required', 'string', 'max:255'],
            'lines.*.qty' => ['nullable', 'integer', 'min:1'],
            'lines.*.reference_no' => ['nullable', 'string', 'max:255'],
            'lines.*.received_at' => ['nullable', 'date'],
            'lines.*.expires_at' => ['nullable', 'date'],
            'lines.*.tag_codes' => ['nullable', 'array'],
            'lines.*.tag_codes.*' => ['string', 'distinct'],
            'lines.*.notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function validatedLines(): array
    {
        $lines = [];
        /** @var array<int, array<string, mixed>> $inputLines */
        $inputLines = $this->validated()['lines'];

        foreach ($inputLines as $index => $line) {
            $sku = (string) $line['sku'];
            $product = Product::query()->where('sku', $sku)->first();

            if (! $product) {
                throw ValidationException::withMessages([
                    "lines.{$index}.sku" => "Product with SKU {$sku} not found.",
                ]);
            }

            if ($product->type === ProductType::Consumable) {
                $qty = isset($line['qty']) && $line['qty'] !== null ? (int) $line['qty'] : null;

                if ($qty === null || $qty < 1) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.qty" => 'Quantity is required for consumable products.',
                    ]);
                }

                $lines[] = [
                    'sku' => $sku,
                    'qty' => $qty,
                    'reference_no' => $line['reference_no'] ?? null,
                    'received_at' => isset($line['received_at']) ? CarbonImmutable::parse($line['received_at']) : null,
                    'expires_at' => isset($line['expires_at']) ? CarbonImmutable::parse($line['expires_at']) : null,
                    'notes' => $line['notes'] ?? null,
                    'tag_codes' => null,
                ];
            } else {
                /** @var array<int, string>|null $tagCodes */
                $tagCodes = $line['tag_codes'] ?? null;

                if (empty($tagCodes)) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.tag_codes" => 'At least one asset tag code is required for asset products.',
                    ]);
                }

                $lines[] = [
                    'sku' => $sku,
                    'qty' => null,
                    'reference_no' => $line['reference_no'] ?? null,
                    'received_at' => isset($line['received_at']) ? CarbonImmutable::parse($line['received_at']) : null,
                    'expires_at' => null,
                    'notes' => $line['notes'] ?? null,
                    'tag_codes' => $tagCodes,
                ];
            }
        }

        return $lines;
    }
}
