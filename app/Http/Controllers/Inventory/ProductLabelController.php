<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ProductLabelRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ProductLabelController extends Controller
{
    public function show(Product $product): Response
    {
        $this->authorize('view', $product);

        $qrSvg = $this->makeQrSvg($product->sku, 256);

        return Inertia::render('inventory/labels/ProductLabel', [
            'product' => (new ProductResource($product))->resolve(),
            'qr_svg' => $qrSvg,
        ]);
    }

    public function sheet(ProductLabelRequest $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $copies = min(50, max(1, $request->integer('copies', 1)));
        $paperSize = in_array($request->string('paper_size')->toString(), ['a4', 'letter'], true)
            ? $request->string('paper_size')->toString()
            : 'a4';
        $gridPreset = in_array(
            $request->string('grid_preset')->toString(),
            ['compact_30', 'standard_24', 'medium_18', 'large_12', 'single_1'],
            true
        ) ? $request->string('grid_preset')->toString() : 'standard_24';

        $showSku = $request->boolean('show_sku', true);
        $showName = $request->boolean('show_name', true);
        $showCategory = $request->boolean('show_category', false);

        $products = $this->resolveProducts($request, $copies);

        $labels = $products->map(function (Product $product) {
            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'type' => $product->type?->value,
                'category' => $product->category?->name,
                'qr_svg' => $this->makeQrSvg($product->sku, 160),
            ];
        })->values();

        return Inertia::render('inventory/labels/BulkLabelSheet', [
            'labels' => $labels,
            'total_products' => $products->count(),
            'initial_config' => [
                'copies' => $copies,
                'paper_size' => $paperSize,
                'grid_preset' => $gridPreset,
                'layout' => $this->getGridPresetConfig($gridPreset, $paperSize),
                'show_sku' => $showSku,
                'show_name' => $showName,
                'show_category' => $showCategory,
            ],
            'filters' => [
                'search' => $request->string('search')->trim()->toString() ?: null,
                'category_id' => $request->integer('category_id') ?: null,
                'type' => $request->string('type')->trim()->toString() ?: null,
            ],
        ]);
    }

    public function pdf(ProductLabelRequest $request): SymfonyResponse
    {
        $this->authorize('viewAny', Product::class);

        $copies = min(50, max(1, $request->integer('copies', 1)));
        $paperSize = in_array($request->string('paper_size')->toString(), ['a4', 'letter'], true)
            ? $request->string('paper_size')->toString()
            : 'a4';
        $gridPreset = in_array(
            $request->string('grid_preset')->toString(),
            ['compact_30', 'standard_24', 'medium_18', 'large_12', 'single_1'],
            true
        ) ? $request->string('grid_preset')->toString() : 'standard_24';

        $showSku = $request->boolean('show_sku', true);
        $showName = $request->boolean('show_name', true);
        $showCategory = $request->boolean('show_category', false);

        $products = $this->resolveProducts($request, $copies);

        $layoutConfig = $this->getGridPresetConfig($gridPreset, $paperSize);

        // Build flat array of items multiplied by copies
        $items = [];
        foreach ($products as $product) {
            $qrSvg = $this->makeQrSvg($product->sku, 160);
            $qrBase64 = base64_encode($qrSvg);

            for ($i = 0; $i < $copies; $i++) {
                $items[] = [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category' => $product->category?->name,
                    'qr_base64' => $qrBase64,
                ];
            }
        }

        $itemsPerPage = $layoutConfig['cols'] * $layoutConfig['rows'];
        $pages = [];

        foreach (array_chunk($items, $itemsPerPage) as $pageItems) {
            $pageRows = [];
            foreach (array_chunk($pageItems, $layoutConfig['cols']) as $rowItems) {
                // Pad last row if needed
                while (count($rowItems) < $layoutConfig['cols']) {
                    $rowItems[] = null;
                }
                $pageRows[] = $rowItems;
            }
            $pages[] = $pageRows;
        }

        $colWidthPct = round(100 / $layoutConfig['cols'], 2);

        $filename = sprintf(
            'pup-prism-labels-%s-%s.pdf',
            $gridPreset,
            CarbonImmutable::now()->format('Y-m-d-His')
        );

        $pdf = Pdf::loadView('labels.sheet', [
            'title' => 'PUP PRISM - Product Labels',
            'pages' => $pages,
            'paper_size' => $paperSize,
            'orientation' => 'portrait',
            'margin_mm' => $layoutConfig['margin_mm'],
            'gap_mm' => $layoutConfig['gap_mm'],
            'cell_height_mm' => $layoutConfig['cell_height_mm'],
            'col_width_pct' => $colWidthPct,
            'qr_size_px' => $layoutConfig['qr_size_px'],
            'show_sku' => $showSku,
            'show_name' => $showName,
            'show_category' => $showCategory,
        ])->setPaper($paperSize, 'portrait');

        return $pdf->download($filename);
    }

    /**
     * @return Collection<int, Product>
     */
    private function resolveProducts(ProductLabelRequest $request, int $copies): Collection
    {
        $productIds = $request->input('product_ids', []);
        $search = Str::squish($request->string('search')->toString());
        $type = $request->string('type')->trim()->toString();
        $categoryId = $request->integer('category_id') ?: null;
        $active = $request->has('active') ? $request->boolean('active') : true;

        $query = Product::query()
            ->with('category:id,name')
            ->when($productIds !== [], fn (Builder $q) => $q->whereIn('id', $productIds))
            ->when($productIds === [] && $search !== '', function (Builder $q) use ($search) {
                foreach (explode(' ', $search) as $term) {
                    $q->where(function (Builder $sub) use ($term) {
                        $sub->whereLike('sku', "%{$term}%")
                            ->orWhereLike('name', "%{$term}%");
                    });
                }
            })
            ->when($productIds === [] && $type !== '', fn (Builder $q) => $q->where('type', $type))
            ->when($productIds === [] && $categoryId !== null, fn (Builder $q) => $q->where('category_id', $categoryId))
            ->when($productIds === [] && $active !== null, fn (Builder $q) => $q->where('is_active', $active));

        $productCount = (clone $query)->count();

        if ($productCount === 0) {
            throw ValidationException::withMessages([
                'product_ids' => 'No products match this label request. Review the selection or filters and try again.',
            ]);
        }

        if ($productCount > ProductLabelRequest::MAX_PRODUCTS || $productCount * $copies > ProductLabelRequest::MAX_LABELS) {
            throw ValidationException::withMessages([
                'copies' => 'This request would create more than 500 labels. Narrow the filters or reduce the copies.',
            ]);
        }

        return $query->orderBy('name')->get();
    }

    private function makeQrSvg(string $value, int $size = 256): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd
        );

        $writer = new Writer($renderer);

        return $writer->writeString($value);
    }

    /**
     * @return array{cols: int, rows: int, margin_mm: int, gap_mm: int, cell_height_mm: int, qr_size_px: int}
     */
    private function getGridPresetConfig(string $preset, string $paperSize): array
    {
        $isLetter = $paperSize === 'letter';

        return match ($preset) {
            'compact_30' => [
                'cols' => 3,
                'rows' => 10,
                'margin_mm' => 6,
                'gap_mm' => 1,
                'cell_height_mm' => $isLetter ? 25 : 26,
                'qr_size_px' => 52,
            ],
            'medium_18' => [
                'cols' => 3,
                'rows' => 6,
                'margin_mm' => 8,
                'gap_mm' => 2,
                'cell_height_mm' => $isLetter ? 41 : 42,
                'qr_size_px' => 82,
            ],
            'large_12' => [
                'cols' => 2,
                'rows' => 6,
                'margin_mm' => 8,
                'gap_mm' => 2,
                'cell_height_mm' => $isLetter ? 41 : 42,
                'qr_size_px' => 90,
            ],
            'single_1' => [
                'cols' => 1,
                'rows' => 1,
                'margin_mm' => 20,
                'gap_mm' => 0,
                'cell_height_mm' => 220,
                'qr_size_px' => 250,
            ],
            default => [ // standard_24
                'cols' => 3,
                'rows' => 8,
                'margin_mm' => 8,
                'gap_mm' => 2,
                'cell_height_mm' => $isLetter ? 30 : 32,
                'qr_size_px' => 68,
            ],
        };
    }
}
