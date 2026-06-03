<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Product;
use App\Models\Requisition;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrashController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $modelType = $request->string('type')->trim()->toString();

        $products = $this->trashedProducts($search, $modelType);
        $bookings = $this->trashedBookings($search, $modelType);
        $requisitions = $this->trashedRequisitions($search, $modelType);

        $merged = collect()
            ->merge($products)
            ->merge($bookings)
            ->merge($requisitions)
            ->sortByDesc('deleted_at')
            ->values();

        $perPage = 15;
        $currentPage = $request->integer('page', 1);
        $total = $merged->count();
        $items = $merged->forPage($currentPage, $perPage)->values();

        return Inertia::render('inventory/Trash', [
            'filters' => [
                'search' => $search,
                'type' => $modelType,
            ],
            'items' => [
                'data' => $items,
                'links' => $this->simpleLinks($currentPage, $total, $perPage, $request),
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function trashedProducts(string $search, string $modelType): array
    {
        if ($modelType !== '' && $modelType !== 'product') {
            return [];
        }

        return Product::query()
            ->onlyTrashed()
            ->with(['deletedBy:id,name,email'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('deleted_at')
            ->limit(50)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'type' => 'product',
                'label' => "{$p->sku} — {$p->name}",
                'meta' => $p->type?->value,
                'deleted_at' => $p->deleted_at?->toIso8601String(),
                'deleted_by' => $p->deletedBy ? ['id' => $p->deletedBy->id, 'name' => $p->deletedBy->name, 'email' => $p->deletedBy->email] : null,
                'deletion_reason' => $p->deletion_reason,
                'restore_url' => route('inventory.products.restore', $p->id, absolute: false),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function trashedBookings(string $search, string $modelType): array
    {
        if ($modelType !== '' && $modelType !== 'booking') {
            return [];
        }

        return Booking::query()
            ->onlyTrashed()
            ->with(['asset.product:id,name', 'deletedBy:id,name,email'])
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('asset', function ($query) use ($search) {
                    $query->where('tag_code', 'like', "%{$search}%")
                        ->orWhereHas('product', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('deleted_at')
            ->limit(50)
            ->get()
            ->map(fn (Booking $b) => [
                'id' => $b->id,
                'type' => 'booking',
                'label' => ($b->asset?->product?->name ?? 'Asset').' — '.$b->status->value,
                'meta' => $b->asset?->tag_code,
                'deleted_at' => $b->deleted_at?->toIso8601String(),
                'deleted_by' => $b->deletedBy ? ['id' => $b->deletedBy->id, 'name' => $b->deletedBy->name, 'email' => $b->deletedBy->email] : null,
                'deletion_reason' => $b->deletion_reason,
                'restore_url' => route('inventory.bookings.restore', $b->id, absolute: false),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function trashedRequisitions(string $search, string $modelType): array
    {
        if ($modelType !== '' && $modelType !== 'requisition') {
            return [];
        }

        return Requisition::query()
            ->onlyTrashed()
            ->with(['requester:id,name,email', 'deletedBy:id,name,email'])
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('requester', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('deleted_at')
            ->limit(50)
            ->get()
            ->map(fn (Requisition $r) => [
                'id' => $r->id,
                'type' => 'requisition',
                'label' => 'Requisition #'.$r->id.' — '.($r->requester?->name ?? 'Unknown'),
                'meta' => $r->status->value,
                'deleted_at' => $r->deleted_at?->toIso8601String(),
                'deleted_by' => $r->deletedBy ? ['id' => $r->deletedBy->id, 'name' => $r->deletedBy->name, 'email' => $r->deletedBy->email] : null,
                'deletion_reason' => $r->deletion_reason,
                'restore_url' => route('inventory.requisitions.restore', $r->id, absolute: false),
            ])
            ->all();
    }

    /**
     * @return array<int, array{url: string|null, label: string, active: bool}>
     */
    private function simpleLinks(int $currentPage, int $total, int $perPage, Request $request): array
    {
        $lastPage = (int) max(1, ceil($total / $perPage));

        $links = [];
        $links[] = [
            'url' => $currentPage > 1 ? $request->fullUrlWithQuery(['page' => $currentPage - 1]) : null,
            'label' => '&laquo; Previous',
            'active' => false,
        ];

        for ($i = 1; $i <= $lastPage; $i++) {
            $links[] = [
                'url' => $request->fullUrlWithQuery(['page' => $i]),
                'label' => (string) $i,
                'active' => $i === $currentPage,
            ];
        }

        $links[] = [
            'url' => $currentPage < $lastPage ? $request->fullUrlWithQuery(['page' => $currentPage + 1]) : null,
            'label' => 'Next &raquo;',
            'active' => false,
        ];

        return $links;
    }
}
