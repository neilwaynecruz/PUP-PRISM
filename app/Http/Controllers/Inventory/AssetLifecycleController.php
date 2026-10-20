<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AssetLifecycleReviewRequest;
use App\Http\Requests\Inventory\AssetLifecycleStoreRequest;
use App\Models\AssetLifecycleEvent;
use App\Services\Inventory\AssetLifecycleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AssetLifecycleController extends Controller
{
    public function store(AssetLifecycleStoreRequest $request, AssetLifecycleService $service): RedirectResponse
    {
        try {
            $service->propose($request->user(), $request->validated());
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['event_type' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The lifecycle change has been submitted for approval.')]);

        return back();
    }

    public function approve(AssetLifecycleReviewRequest $request, AssetLifecycleEvent $assetLifecycleEvent, AssetLifecycleService $service): RedirectResponse
    {
        try {
            $service->approve($request->user(), $assetLifecycleEvent);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['lifecycle' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The asset lifecycle has been updated.')]);

        return back();
    }

    public function reject(AssetLifecycleReviewRequest $request, AssetLifecycleEvent $assetLifecycleEvent, AssetLifecycleService $service): RedirectResponse
    {
        try {
            $service->reject($request->user(), $assetLifecycleEvent, $request->validated('notes'));
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['lifecycle' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The lifecycle change has been rejected.')]);

        return back();
    }
}
