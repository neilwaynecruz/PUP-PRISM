<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\BatchReceiveStockRequest;
use App\Http\Requests\Inventory\ReceiveStockRequest;
use App\Models\Product;
use App\Models\PurchaseOrderLine;
use App\Services\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ReceivingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('inventory/receiving/Index');
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
            } else {
                $product = Product::query()->where('sku', $validated['sku'])->firstOrFail();

                $inventory->receive($request->user(), $product, $payload, ipAddress: $request->ip());
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
        } catch (RuntimeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back()->withInput();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Batch stock received.')]);

        return back();
    }
}
