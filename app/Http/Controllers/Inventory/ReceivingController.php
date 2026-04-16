<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ReceiveStockRequest;
use App\Models\Product;
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

        $product = Product::query()->where('sku', $validated['sku'])->firstOrFail();

        $payload = [
            ...$validated,
            'received_at' => isset($validated['received_at']) ? CarbonImmutable::parse($validated['received_at']) : null,
            'expires_at' => isset($validated['expires_at']) ? CarbonImmutable::parse($validated['expires_at']) : null,
        ];

        try {
            $inventory->receive($request->user(), $product, $payload, ipAddress: $request->ip());
        } catch (RuntimeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back()->withInput();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock received.')]);

        return back();
    }
}
