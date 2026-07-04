<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class GlobalSearchService
{
    private const int LIMIT_PER_TYPE = 5;

    /**
     * @return list<array{type: string, id: int, title: string, subtitle: string|null, url: string}>
     */
    public function search(User $user, string $query): array
    {
        $term = trim($query);

        if ($term === '') {
            return [];
        }

        $results = [];

        if ($user->can('viewAny', Product::class)) {
            $results = array_merge($results, $this->searchProducts($term));
        }

        if ($user->can('viewAny', Asset::class)) {
            $results = array_merge($results, $this->searchAssets($term));
        }

        if ($user->can('viewAny', Requisition::class)) {
            $results = array_merge($results, $this->searchRequisitions($term));
        }

        if ($user->can('viewAny', Booking::class)) {
            $results = array_merge($results, $this->searchBookings($term));
        }

        if ($user->can('viewAny', PurchaseOrder::class)) {
            $results = array_merge($results, $this->searchPurchaseOrders($term));
        }

        if ($user->hasRole('Admin')) {
            $results = array_merge($results, $this->searchUsers($term));
        }

        return $results;
    }

    /**
     * @return list<array{type: string, id: int, title: string, subtitle: string|null, url: string}>
     */
    private function searchProducts(string $term): array
    {
        return Product::query()
            ->where(function (Builder $query) use ($term) {
                $query->where('sku', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(self::LIMIT_PER_TYPE)
            ->get(['id', 'sku', 'name', 'type'])
            ->map(fn (Product $product) => [
                'type' => 'product',
                'id' => $product->id,
                'title' => $product->name,
                'subtitle' => "{$product->sku} · {$product->type->value}",
                'url' => route('inventory.products.show', $product, absolute: false),
            ])
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, subtitle: string|null, url: string}>
     */
    private function searchAssets(string $term): array
    {
        return Asset::query()
            ->with('product:id,name')
            ->where(function (Builder $query) use ($term) {
                $query->where('tag_code', 'like', "%{$term}%")
                    ->orWhereHas('product', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
            })
            ->orderBy('tag_code')
            ->limit(self::LIMIT_PER_TYPE)
            ->get(['id', 'product_id', 'tag_code', 'status'])
            ->map(fn (Asset $asset) => [
                'type' => 'asset',
                'id' => $asset->id,
                'title' => $asset->tag_code,
                'subtitle' => trim(($asset->product?->name ?? 'Asset').' · '.$asset->status->value),
                'url' => route('inventory.products.show', $asset->product_id, absolute: false),
            ])
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, subtitle: string|null, url: string}>
     */
    private function searchRequisitions(string $term): array
    {
        return Requisition::query()
            ->with('requester:id,name,email')
            ->where(function (Builder $query) use ($term) {
                if (ctype_digit($term)) {
                    $query->whereKey((int) $term);
                } else {
                    $query->whereHas('requester', function (Builder $query) use ($term) {
                        $query->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    });
                }
            })
            ->orderByDesc('created_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get(['id', 'status', 'requester_id', 'created_at'])
            ->map(fn (Requisition $requisition) => [
                'type' => 'requisition',
                'id' => $requisition->id,
                'title' => 'Requisition #'.$requisition->id,
                'subtitle' => trim(($requisition->requester?->name ?? $requisition->requester?->email ?? 'Unknown').' · '.$requisition->status->value),
                'url' => route('inventory.requisitions.show', $requisition, absolute: false),
            ])
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, subtitle: string|null, url: string}>
     */
    private function searchBookings(string $term): array
    {
        return Booking::query()
            ->with(['asset:id,tag_code,product_id', 'asset.product:id,name', 'requester:id,name,email'])
            ->where(function (Builder $query) use ($term) {
                if (ctype_digit($term)) {
                    $query->whereKey((int) $term);
                } else {
                    $query->whereHas('asset', fn (Builder $query) => $query->where('tag_code', 'like', "%{$term}%"))
                        ->orWhereHas('requester', function (Builder $query) use ($term) {
                            $query->where('name', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%");
                        });
                }
            })
            ->orderByDesc('start_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get(['id', 'status', 'asset_id', 'requester_id', 'start_at'])
            ->map(fn (Booking $booking) => [
                'type' => 'booking',
                'id' => $booking->id,
                'title' => 'Booking #'.$booking->id,
                'subtitle' => trim(($booking->asset?->tag_code ?? 'Asset').' · '.$booking->status->value),
                'url' => route('inventory.bookings.show', $booking, absolute: false),
            ])
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, subtitle: string|null, url: string}>
     */
    private function searchPurchaseOrders(string $term): array
    {
        return PurchaseOrder::query()
            ->with('supplier:id,name')
            ->where(function (Builder $query) use ($term) {
                if (ctype_digit($term)) {
                    $query->whereKey((int) $term);
                } else {
                    $query->where('reference_no', 'like', "%{$term}%")
                        ->orWhereHas('supplier', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
                }
            })
            ->orderByDesc('created_at')
            ->limit(self::LIMIT_PER_TYPE)
            ->get(['id', 'reference_no', 'status', 'supplier_id'])
            ->map(fn (PurchaseOrder $purchaseOrder) => [
                'type' => 'purchase_order',
                'id' => $purchaseOrder->id,
                'title' => $purchaseOrder->reference_no ?? 'PO #'.$purchaseOrder->id,
                'subtitle' => trim(($purchaseOrder->supplier?->name ?? 'Supplier').' · '.$purchaseOrder->status->value),
                'url' => route('inventory.purchase-orders.show', $purchaseOrder, absolute: false),
            ])
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, subtitle: string|null, url: string}>
     */
    private function searchUsers(string $term): array
    {
        return User::query()
            ->where(function (Builder $query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(self::LIMIT_PER_TYPE)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $searchedUser) => [
                'type' => 'user',
                'id' => $searchedUser->id,
                'title' => $searchedUser->name,
                'subtitle' => $searchedUser->email,
                'url' => route('admin.users.edit', $searchedUser, absolute: false),
            ])
            ->all();
    }
}
