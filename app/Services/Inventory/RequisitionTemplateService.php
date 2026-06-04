<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\RequisitionTemplate;
use App\Models\User;
use Illuminate\Support\Collection;

class RequisitionTemplateService
{
    /**
     * @param  array<int, array{sku: string, qty_requested: int|string}>  $lines
     * @return array<int, array{sku: string, name: string|null, qty_requested: int}>
     */
    public function normalizeLines(array $lines): array
    {
        /** @var Collection<string, Product> $products */
        $products = Product::query()
            ->whereIn('sku', collect($lines)->pluck('sku')->filter()->all())
            ->get(['id', 'sku', 'name'])
            ->keyBy('sku');

        return collect($lines)
            ->map(function (array $line) use ($products): array {
                $sku = trim((string) $line['sku']);

                return [
                    'sku' => $sku,
                    'name' => $products->get($sku)?->name,
                    'qty_requested' => (int) $line['qty_requested'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     notes: string|null,
     *     updated_at: string|null,
     *     line_count: int,
     *     lines: array<int, array{
     *         sku: string,
     *         name: string|null,
     *         qty_requested: int,
     *         availability: array{available: bool, message: string|null}
     *     }>
     * }>
     */
    public function templatesForUser(User $user): array
    {
        /** @var Collection<int, RequisitionTemplate> $templates */
        $templates = RequisitionTemplate::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->orderByDesc('updated_at')
            ->get();

        $templateSkus = $templates
            ->flatMap(fn (RequisitionTemplate $template) => collect($template->lines ?? [])->pluck('sku'))
            ->filter()
            ->unique()
            ->values();

        /** @var Collection<string, Product> $products */
        $products = Product::query()
            ->withTrashed()
            ->whereIn('sku', $templateSkus->all())
            ->get(['id', 'sku', 'name', 'is_active', 'deleted_at'])
            ->keyBy('sku');

        return $templates
            ->map(function (RequisitionTemplate $template) use ($products): array {
                $lines = collect($template->lines ?? [])
                    ->map(function (array $line) use ($products): array {
                        $sku = (string) ($line['sku'] ?? '');
                        $product = $products->get($sku);
                        $available = $product !== null && ! $product->trashed() && (bool) $product->is_active;

                        return [
                            'sku' => $sku,
                            'name' => $product?->name ?? ($line['name'] ?? null),
                            'qty_requested' => (int) ($line['qty_requested'] ?? 0),
                            'availability' => [
                                'available' => $available,
                                'message' => $available
                                    ? null
                                    : ($product === null || $product->trashed()
                                        ? 'This SKU is no longer in the catalog.'
                                        : 'This product is currently inactive.'),
                            ],
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'id' => (int) $template->id,
                    'name' => $template->name,
                    'notes' => $template->notes,
                    'updated_at' => $template->updated_at?->toIso8601String(),
                    'line_count' => count($lines),
                    'lines' => $lines,
                ];
            })
            ->values()
            ->all();
    }
}
