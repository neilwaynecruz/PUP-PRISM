<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\PurchaseOrderCancelRequest;
use App\Http\Requests\Inventory\PurchaseOrderReceiveRequest;
use App\Http\Requests\Inventory\PurchaseOrderSendRequest;
use App\Http\Requests\Inventory\PurchaseOrderStoreRequest;
use App\Http\Resources\PurchaseOrderCollection;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Services\Procurement\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $supplierId = $request->integer('supplier_id') ?: null;
        $dateFrom = $request->string('date_from')->trim()->toString();
        $dateTo = $request->string('date_to')->trim()->toString();

        $purchaseOrders = PurchaseOrder::query()
            ->with(['supplier:id,name', 'requester:id,name', 'approver:id,name'])
            ->withCount('lines')
            ->withSum('lines as ordered_qty_sum', 'qty_ordered')
            ->withSum('lines as received_qty_sum', 'qty_received')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->whereLike('po_number', "%{$search}%")
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->whereLike('name', "%{$search}%"));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($supplierId !== null, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('inventory/purchase-orders/Index', [
            'filters' => [
                'search' => $search,
                'status' => $status,
                'supplier_id' => $supplierId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'purchaseOrders' => (new PurchaseOrderCollection($purchaseOrders))->toArray($request),
            'suppliers' => $this->service->supplierOptions(),
            'statuses' => collect(PurchaseOrderStatus::cases())
                ->map(fn (PurchaseOrderStatus $statusCase) => [
                    'value' => $statusCase->value,
                    'label' => $statusCase->label(),
                ])
                ->values()
                ->all(),
            'can' => [
                'create' => $request->user()?->can('create', PurchaseOrder::class) ?? false,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PurchaseOrder::class);

        $initialSupplierId = null;
        $initialLines = null;

        if ($request->filled('product_id')) {
            $productId = $request->integer('product_id');
            $product = Product::query()->where('is_active', true)->find($productId);

            if ($product instanceof Product) {
                $initialSupplierId = $product->supplier_id;
                $initialQty = max(1, $request->integer('qty', 1));
                $initialLines = [
                    [
                        'product_id' => (string) $product->id,
                        'qty_ordered' => (string) $initialQty,
                        'unit_price' => $product->unit_price !== null ? (string) round((float) $product->unit_price, 2) : '',
                    ],
                ];
            }
        }

        return Inertia::render('inventory/purchase-orders/Create', [
            'suppliers' => $this->service->supplierOptions(onlyActive: true),
            'products' => $this->service->productOptions(),
            'initialSupplierId' => $initialSupplierId,
            'initialLines' => $initialLines,
        ]);
    }

    public function store(PurchaseOrderStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $purchaseOrder = $this->service->createDraft($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase order created.')]);

        return to_route('inventory.purchase-orders.show', $purchaseOrder);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load([
            'supplier',
            'requester:id,name,email',
            'approver:id,name,email',
            'lines.product:id,sku,name,type,variant,unit_of_measurement_id,supplier_id,category_id',
            'lines.product.unitOfMeasurement:id,name,abbreviation',
            'lines.product.category:id,name',
            'lines.product.stock:id,product_id,on_hand_qty',
        ]);

        return Inertia::render('inventory/purchase-orders/Show', [
            'purchaseOrder' => (new PurchaseOrderResource($purchaseOrder))->resolve($request),
            'can' => [
                'send' => $request->user()?->can('send', $purchaseOrder) ?? false,
                'receive' => $request->user()?->can('receive', $purchaseOrder) ?? false,
                'cancel' => $request->user()?->can('cancel', $purchaseOrder) ?? false,
            ],
        ]);
    }

    public function send(PurchaseOrderSendRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('send', $purchaseOrder);

        $sentPo = $this->service->send(
            purchaseOrder: $purchaseOrder,
            actor: $request->user(),
            notes: $request->string('notes')->trim()->toString(),
        );

        if (! $sentPo instanceof PurchaseOrder) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Only draft purchase orders can be sent.'),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Purchase order sent to supplier.'),
        ]);

        return back();
    }

    public function receive(PurchaseOrderReceiveRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('receive', $purchaseOrder);

        if (! $purchaseOrder->canReceive()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Only sent or partially received purchase orders can accept receipts.'),
            ]);

            return back();
        }

        try {
            $updatedPo = $this->service->receive(
                purchaseOrder: $purchaseOrder,
                actor: $request->user(),
                validatedLines: $request->validatedLines(),
                ipAddress: $request->ip(),
                idempotencyKey: $request->string('idempotency_key')->trim()->toString() ?: null,
            );
        } catch (\RuntimeException $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $updatedPo->status === PurchaseOrderStatus::Received
                ? __('Purchase order fully received.')
                : __('Purchase order receipt recorded.'),
        ]);

        return back();
    }

    public function cancel(PurchaseOrderCancelRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('cancel', $purchaseOrder);

        $outcome = $this->service->cancel(
            purchaseOrder: $purchaseOrder,
            reason: $request->string('reason')->trim()->toString(),
        );

        Inertia::flash('toast', [
            'type' => $outcome['status'],
            'message' => $outcome['message'],
        ]);

        return back();
    }

    public function generate(Request $request): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $generatedPurchaseOrders = $this->service->generateFromAlerts($request->user());

        if ($generatedPurchaseOrders->isEmpty()) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('No draft purchase orders were generated from current alerts.'),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(
                '{1} Created :count draft purchase order.|[0,*] Created :count draft purchase orders.',
                $generatedPurchaseOrders->count(),
                ['count' => $generatedPurchaseOrders->count()],
            ),
        ]);

        return to_route('inventory.purchase-orders.index');
    }

    public function generateFromForecasts(Request $request): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $generatedPurchaseOrders = $this->service->generateFromForecasts($request->user());

        if ($generatedPurchaseOrders->isEmpty()) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('No draft purchase orders were generated from forecast alerts.'),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(
                '{1} Created :count draft purchase order from forecast alerts.|[0,*] Created :count draft purchase orders from forecast alerts.',
                $generatedPurchaseOrders->count(),
                ['count' => $generatedPurchaseOrders->count()],
            ),
        ]);

        return to_route('inventory.purchase-orders.index');
    }
}
