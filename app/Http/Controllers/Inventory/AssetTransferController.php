<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AssetTransferReviewRequest;
use App\Http\Requests\Inventory\AssetTransferStoreRequest;
use App\Models\AssetTransfer;
use App\Services\Inventory\AssetTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssetTransferController extends Controller
{
    public function store(AssetTransferStoreRequest $request, AssetTransferService $service): RedirectResponse
    {
        try {
            $service->create($request->user(), $request->validated());
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['to_location_id' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The asset transfer has been submitted for approval.')]);

        return back();
    }

    public function approve(AssetTransferReviewRequest $request, AssetTransfer $assetTransfer, AssetTransferService $service): RedirectResponse
    {
        try {
            $service->approve($request->user(), $assetTransfer);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['transfer' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The asset has been transferred successfully.')]);

        return back();
    }

    public function reject(AssetTransferReviewRequest $request, AssetTransfer $assetTransfer, AssetTransferService $service): RedirectResponse
    {
        try {
            $service->reject($request->user(), $assetTransfer, $request->validated('notes'));
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['transfer' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The asset transfer has been rejected.')]);

        return back();
    }

    public function cancel(Request $request, AssetTransfer $assetTransfer, AssetTransferService $service): RedirectResponse
    {
        $this->authorize('cancel', $assetTransfer);
        $request->validate(['notes' => ['nullable', 'string', 'max:4000']]);

        try {
            $service->cancel($request->user(), $assetTransfer, $request->string('notes')->trim()->toString() ?: null);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['transfer' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The asset transfer has been cancelled.')]);

        return back();
    }
}
