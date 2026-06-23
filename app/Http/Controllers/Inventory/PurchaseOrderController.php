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
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Notifications\PurchaseOrderSentNotification;
use App\Services\AuditLogService;
use App\Services\Inventory\InventoryService;
use App\Services\Procurement\PurchaseOrderGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
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
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('po_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'like', "%{$search}%"));
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
            'suppliers' => $this->supplierOptions(),
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

    public function create(): Response
    {
        $this->authorize('create', PurchaseOrder::class);

        return Inertia::render('inventory/purchase-orders/Create', [
            'suppliers' => $this->supplierOptions(onlyActive: true),
            'products' => $this->productOptions(),
        ]);
    }

    public function store(PurchaseOrderStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $validated = $request->validated();
        $purchaseOrder = DB::transaction(function () use ($request, $validated): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()->create([
                'supplier_id' => $validated['supplier_id'],
                'po_number' => $this->nextPoNumber(),
                'status' => PurchaseOrderStatus::Draft,
                'subtotal' => 0,
                'tax' => round((float) ($validated['tax'] ?? 0), 2),
                'total_amount' => 0,
                'requested_by' => $request->user()?->id,
                'approved_by' => null,
                'expected_delivery_at' => isset($validated['expected_delivery_at'])
                    ? CarbonImmutable::parse((string) $validated['expected_delivery_at'])
                    : null,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['lines'] as $line) {
                $product = Product::query()->findOrFail((int) $line['product_id']);
                $unitPrice = round((float) ($line['unit_price'] ?? $product->unit_price ?? 0), 2);
                $quantity = (int) $line['qty_ordered'];

                PurchaseOrderLine::query()->create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $product->id,
                    'qty_ordered' => $quantity,
                    'qty_received' => 0,
                    'unit_price' => $unitPrice,
                    'subtotal' => round($quantity * $unitPrice, 2),
                ]);
            }

            $purchaseOrder->recalculateTotals();

            return $purchaseOrder->fresh(['supplier', 'requester', 'approver', 'lines.product']) ?? $purchaseOrder;
        });

        AuditLogService::logCreated($purchaseOrder);

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
            'lines.product:id,sku,name,type,supplier_id',
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

        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Only draft purchase orders can be sent.'),
            ]);

            return back();
        }

        $purchaseOrder->forceFill([
            'status' => PurchaseOrderStatus::Sent,
            'sent_at' => now(),
            'approved_by' => $request->user()?->id,
            'notes' => $this->mergeNotes(
                $purchaseOrder->notes,
                $request->string('notes')->trim()->toString(),
            ),
        ])->save();

        $purchaseOrder->load('supplier');

        if (filled($purchaseOrder->supplier?->email)) {
            Notification::route('mail', (string) $purchaseOrder->supplier?->email)
                ->notify(new PurchaseOrderSentNotification($purchaseOrder));
        }

        AuditLogService::logUpdated($purchaseOrder, [
            'status' => PurchaseOrderStatus::Draft->value,
            'sent_at' => null,
            'approved_by' => null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Purchase order sent to supplier.'),
        ]);

        return back();
    }

    public function receive(
        PurchaseOrderReceiveRequest $request,
        PurchaseOrder $purchaseOrder,
        InventoryService $inventory,
    ): RedirectResponse {
        $this->authorize('receive', $purchaseOrder);

        if (! $purchaseOrder->canReceive()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Only sent or partially received purchase orders can accept receipts.'),
            ]);

            return back();
        }

        $validatedLines = $request->validatedLines();

        foreach ($validatedLines as $line) {
            $purchaseOrderLine = PurchaseOrderLine::query()->findOrFail($line['purchase_order_line_id']);

            $inventory->receiveAgainstPurchaseOrderLine(
                $request->user(),
                $purchaseOrderLine,
                $line,
                ipAddress: $request->ip(),
            );
        }

        $purchaseOrder->refresh();
        $purchaseOrder->load(['lines.product', 'supplier']);

        AuditLogService::logUpdated($purchaseOrder, [
            'status' => $purchaseOrder->getOriginal('status'),
            'received_at' => $purchaseOrder->getOriginal('received_at'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $purchaseOrder->status === PurchaseOrderStatus::Received
                ? __('Purchase order fully received.')
                : __('Purchase order receipt recorded.'),
        ]);

        return back();
    }

    public function cancel(PurchaseOrderCancelRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('cancel', $purchaseOrder);

        if ($purchaseOrder->status === PurchaseOrderStatus::Received) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Received purchase orders cannot be cancelled.'),
            ]);

            return back();
        }

        $oldValues = $purchaseOrder->only(['status', 'notes']);

        $purchaseOrder->forceFill([
            'status' => PurchaseOrderStatus::Cancelled,
            'notes' => $this->mergeNotes(
                $purchaseOrder->notes,
                'Cancellation reason: '.$request->string('reason')->trim()->toString(),
            ),
        ])->save();

        AuditLogService::logUpdated($purchaseOrder, $oldValues);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Purchase order cancelled.'),
        ]);

        return back();
    }

    public function generate(Request $request, PurchaseOrderGenerator $generator): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $generatedPurchaseOrders = $generator->generateFromAlerts($request->user());

        if ($generatedPurchaseOrders->isEmpty()) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('No draft purchase orders were generated from current alerts.'),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Generated :count draft purchase order(s).', ['count' => $generatedPurchaseOrders->count()]),
        ]);

        return to_route('inventory.purchase-orders.index');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function supplierOptions(bool $onlyActive = false): array
    {
        return Supplier::query()
            ->when($onlyActive, fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Supplier $supplier) => [
                'id' => (int) $supplier->id,
                'name' => $supplier->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productOptions(): array
    {
        return Product::query()
            ->with(['supplier:id,name'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'type', 'supplier_id', 'unit_price'])
            ->map(fn (Product $product) => [
                'id' => (int) $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'type' => $product->type?->value,
                'supplier_id' => $product->supplier_id,
                'supplier' => $product->supplier?->name,
                'unit_price' => $product->unit_price,
            ])
            ->all();
    }

    private function nextPoNumber(): string
    {
        $datePrefix = now()->format('Ymd');
        $latest = PurchaseOrder::query()
            ->where('po_number', 'like', "PO-{$datePrefix}-%")
            ->orderByDesc('po_number')
            ->value('po_number');

        $nextSequence = $latest !== null
            ? ((int) substr($latest, -4)) + 1
            : 1;

        return sprintf('PO-%s-%04d', $datePrefix, $nextSequence);
    }

    private function mergeNotes(?string $existingNotes, ?string $nextNote): ?string
    {
        $segments = array_values(array_filter([
            $existingNotes ? trim($existingNotes) : null,
            $nextNote ? trim($nextNote) : null,
        ]));

        if ($segments === []) {
            return null;
        }

        return implode("\n\n", $segments);
    }
}
