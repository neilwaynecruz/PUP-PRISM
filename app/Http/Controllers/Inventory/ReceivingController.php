<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\BatchReceiveStockRequest;
use App\Http\Requests\Inventory\ReceiveStockRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\AuditLogService;
use App\Services\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ReceivingController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->with(['category:id,name', 'supplier:id,name', 'stock:id,product_id,on_hand_qty'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'type', 'category_id', 'supplier_id', 'reorder_threshold', 'unit_price'])
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'type' => $product->type?->value,
                'category' => $product->category?->name,
                'supplier' => $product->supplier?->name,
                'on_hand_qty' => (int) ($product->stock?->on_hand_qty ?? 0),
                'reorder_threshold' => (int) ($product->reorder_threshold ?? 0),
                'unit_price' => $product->unit_price !== null ? round((float) $product->unit_price, 2) : null,
            ])
            ->values()
            ->all();

        $purchaseOrders = PurchaseOrder::query()
            ->with([
                'supplier:id,name,contact_person,email,phone',
                'lines.product:id,sku,name,type,variant,unit_of_measurement_id,supplier_id,category_id',
                'lines.product.unitOfMeasurement:id,name,abbreviation',
                'lines.product.category:id,name',
                'lines.product.stock:id,product_id,on_hand_qty',
            ])
            ->whereIn('status', [PurchaseOrderStatus::Sent, PurchaseOrderStatus::Partial])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (PurchaseOrder $po): array => (new PurchaseOrderResource($po))->resolve($request))
            ->values()
            ->all();

        return Inertia::render('inventory/receiving/Index', [
            'products' => $products,
            'purchaseOrders' => $purchaseOrders,
            'initialPurchaseOrderId' => $request->integer('purchase_order_id') ?: null,
        ]);
    }

    public function store(ReceiveStockRequest $request, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validated();
        $payload = [
            ...$validated,
            'received_at' => isset($validated['received_at']) ? CarbonImmutable::parse($validated['received_at']) : null,
            'expires_at' => isset($validated['expires_at']) ? CarbonImmutable::parse($validated['expires_at']) : null,
        ];

        try {
            if (! empty($validated['purchase_order_line_id'])) {
                /** @var PurchaseOrderLine $purchaseOrderLine */
                $purchaseOrderLine = PurchaseOrderLine::query()->findOrFail($validated['purchase_order_line_id']);

                $inventory->receiveAgainstPurchaseOrderLine(
                    $request->user(),
                    $purchaseOrderLine,
                    [
                        'qty_received' => $validated['qty'] ?? null,
                        'tag_codes' => $validated['tag_codes'] ?? null,
                        'reference_no' => $validated['reference_no'] ?? null,
                        'received_at' => $payload['received_at'],
                        'expires_at' => $payload['expires_at'],
                        'notes' => $validated['notes'] ?? null,
                    ],
                    ipAddress: $request->ip(),
                );

                $product = $purchaseOrderLine->product;
                AuditLogService::logCustom(
                    'receive',
                    __('Received stock against PO line for :sku.', ['sku' => $product?->sku ?? 'product']),
                    $product,
                    null,
                    [
                        'purchase_order_line_id' => $purchaseOrderLine->id,
                        'qty' => $validated['qty'] ?? null,
                        'reference_no' => $validated['reference_no'] ?? null,
                    ],
                );
            } else {
                $product = Product::query()->where('sku', $validated['sku'])->firstOrFail();

                $inventory->receive($request->user(), $product, $payload, ipAddress: $request->ip());

                AuditLogService::logCustom(
                    'receive',
                    __('Received stock for :sku.', ['sku' => $product->sku]),
                    $product,
                    null,
                    [
                        'qty' => $validated['qty'] ?? null,
                        'tag_codes' => $validated['tag_codes'] ?? null,
                        'reference_no' => $validated['reference_no'] ?? null,
                    ],
                );
            }
        } catch (RuntimeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back()->withInput();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock received.')]);

        return back();
    }

    public function storeBatch(BatchReceiveStockRequest $request, InventoryService $inventory): RedirectResponse
    {
        try {
            $lines = $request->validatedLines();

            $inventory->batchReceive($request->user(), $lines, ipAddress: $request->ip());

            AuditLogService::logCustom(
                'receive_batch',
                trans_choice(
                    '{1} Received :count stock line.|[0,*] Received :count stock lines.',
                    count($lines),
                    ['count' => count($lines)],
                ),
                null,
                null,
                ['line_count' => count($lines)],
            );
        } catch (RuntimeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back()->withInput();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Batch stock received.')]);

        return back();
    }
}
