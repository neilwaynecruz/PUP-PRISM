<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SupplierStoreRequest;
use App\Http\Requests\Inventory\SupplierUpdateRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Http\Resources\SupplierCollection;
use App\Http\Resources\SupplierResource;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\AuditLogService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Supplier::class);

        $search = $request->string('search')->trim()->toString();
        $active = $request->has('active') ? $request->boolean('active') : null;

        $suppliers = Supplier::query()
            ->withCount(['products', 'purchaseOrders'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($active !== null, fn ($query) => $query->where('is_active', $active))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('inventory/suppliers/Index', [
            'filters' => [
                'search' => $search,
                'active' => $active,
            ],
            'suppliers' => (new SupplierCollection($suppliers))->toArray($request),
            'can' => [
                'create' => $request->user()?->can('create', Supplier::class) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Supplier::class);

        return Inertia::render('inventory/suppliers/Create');
    }

    public function store(SupplierStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = Supplier::query()->create($request->validated());

        AuditLogService::logCreated($supplier);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier created.')]);

        return to_route('inventory.suppliers.show', $supplier);
    }

    public function show(Request $request, Supplier $supplier): Response
    {
        $this->authorize('view', $supplier);

        $supplier->loadCount(['products', 'purchaseOrders']);

        $recentPurchaseOrders = PurchaseOrder::query()
            ->where('supplier_id', $supplier->id)
            ->with(['supplier:id,name', 'requester:id,name', 'approver:id,name'])
            ->withCount('lines')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (PurchaseOrder $purchaseOrder) => (new PurchaseOrderResource($purchaseOrder))->resolve($request))
            ->all();

        $products = $supplier->products()
            ->with(['category:id,name', 'stock:id,product_id,on_hand_qty'])
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(fn ($product) => [
                'id' => (int) $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'type' => $product->type?->value,
                'category' => $product->category?->name,
                'on_hand_qty' => $product->stock?->on_hand_qty,
                'reorder_threshold' => $product->reorder_threshold,
                'unit_price' => $product->unit_price,
            ])
            ->all();

        return Inertia::render('inventory/suppliers/Show', [
            'supplier' => (new SupplierResource($supplier))->resolve($request),
            'products' => $products,
            'recentPurchaseOrders' => $recentPurchaseOrders,
            'can' => [
                'edit' => $request->user()?->can('update', $supplier) ?? false,
                'delete' => $request->user()?->can('delete', $supplier) ?? false,
                'createPurchaseOrder' => $request->user()?->can('createPurchaseOrder', $supplier) ?? false,
            ],
        ]);
    }

    public function edit(Request $request, Supplier $supplier): Response
    {
        $this->authorize('update', $supplier);

        return Inertia::render('inventory/suppliers/Edit', [
            'supplier' => (new SupplierResource($supplier))->resolve($request),
            'can' => [
                'delete' => $request->user()?->can('delete', $supplier) ?? false,
            ],
        ]);
    }

    public function update(SupplierUpdateRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $oldValues = $supplier->toArray();
        $supplier->update($request->validated());

        AuditLogService::logUpdated($supplier, $oldValues);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier updated.')]);

        return to_route('inventory.suppliers.edit', $supplier);
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        if ($supplier->products()->exists() || $supplier->purchaseOrders()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This supplier is already referenced by products or purchase orders. Deactivate it instead.'),
            ]);

            return back();
        }

        try {
            AuditLogService::logDeleted($supplier);
            $supplier->delete();
        } catch (QueryException $exception) {
            report($exception);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Unable to delete this supplier because it is still referenced elsewhere.'),
            ]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier deleted.')]);

        return to_route('inventory.suppliers.index');
    }
}
