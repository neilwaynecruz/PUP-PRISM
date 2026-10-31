<?php

namespace App\Services\Reports;

use App\Models\Category;
use App\Models\Origin;
use App\Models\Product;
use App\Models\UnitOfMeasurement;
use App\Services\Reports\Support\TableReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ProductInventoryReportService extends AbstractTableReportService
{
    public function build(Request $request): TableReport
    {
        $search = $request->string('search')->trim()->toString();
        $type = $request->string('type')->trim()->toString();
        $categoryId = $request->integer('category_id') ?: null;
        $originId = $request->integer('origin_id') ?: null;
        $unitId = $request->integer('unit_of_measurement_id') ?: null;
        $active = $request->has('active') ? $request->boolean('active') : null;

        $query = Product::query()
            ->with([
                'category:id,name',
                'origin:id,name',
                'unitOfMeasurement:id,name,abbreviation',
                'stock:id,product_id,on_hand_qty',
            ])
            ->withCount('assets')
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($builder) use ($search) {
                    $builder->whereLike('sku', "%{$search}%")
                        ->orWhereLike('name', "%{$search}%")
                        ->orWhereLike('variant', "%{$search}%")
                        ->orWhereLike('brand', "%{$search}%")
                        ->orWhereLike('model', "%{$search}%");
                });
            })
            ->when($type !== '', fn ($builder) => $builder->where('type', $type))
            ->when($categoryId !== null, fn ($builder) => $builder->where('category_id', $categoryId))
            ->when($originId !== null, fn ($builder) => $builder->where('origin_id', $originId))
            ->when($unitId !== null, fn ($builder) => $builder->where('unit_of_measurement_id', $unitId))
            ->when($active !== null, fn ($builder) => $builder->where('is_active', $active))
            ->orderBy('name');

        $rows = (function () use ($query) {
            foreach ($query->lazy(200) as $product) {
                yield [
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'type' => $product->type->value,
                    'category' => $product->category?->name ?? '',
                    'variant' => $product->variant ?? '',
                    'unit' => $product->unitOfMeasurement?->abbreviation ?? '',
                    'origin' => $product->origin?->name ?? '',
                    'on_hand_qty' => $product->stock?->on_hand_qty ?? '',
                    'assets_count' => $product->assets_count,
                    'reorder_threshold' => $product->reorder_threshold,
                    'status' => $product->is_active ? 'Active' : 'Inactive',
                ];
            }
        })();

        return new TableReport(
            title: 'Product Inventory Listing',
            filenameBase: 'product-inventory-listing',
            filters: $this->normalizeFilters([
                'Search' => $search,
                'Type' => $type,
                'Category' => $categoryId ? Category::query()->whereKey($categoryId)->value('name') : null,
                'Origin' => $originId ? Origin::query()->whereKey($originId)->value('name') : null,
                'Unit' => $unitId ? UnitOfMeasurement::query()->whereKey($unitId)->value('name') : null,
                'Status' => $active === null ? null : ($active ? 'Active' : 'Inactive'),
            ]),
            columns: [
                'sku' => 'SKU',
                'name' => 'Name',
                'type' => 'Type',
                'category' => 'Category',
                'variant' => 'Variant / specification',
                'unit' => 'Unit',
                'origin' => 'Origin',
                'on_hand_qty' => 'On hand',
                'assets_count' => 'Assets',
                'reorder_threshold' => 'Reorder threshold',
                'status' => 'Status',
            ],
            rows: $rows,
            generatedBy: $this->generatedBy($request),
            generatedAt: CarbonImmutable::now(),
        );
    }
}
