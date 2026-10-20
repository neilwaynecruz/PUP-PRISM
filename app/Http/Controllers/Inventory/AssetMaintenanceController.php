<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AssetMaintenanceStoreRequest;
use App\Http\Requests\Inventory\AssetMaintenanceTransitionRequest;
use App\Models\AssetMaintenance;
use App\Services\Inventory\AssetMaintenanceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AssetMaintenanceController extends Controller
{
    public function store(AssetMaintenanceStoreRequest $request, AssetMaintenanceService $service): RedirectResponse
    {
        try {
            $service->create($request->user(), $request->validated());
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['asset_id' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The maintenance request has been created.')]);

        return back();
    }

    public function transition(
        AssetMaintenanceTransitionRequest $request,
        AssetMaintenance $assetMaintenance,
        AssetMaintenanceService $service,
    ): RedirectResponse {
        try {
            $service->transition(
                $assetMaintenance,
                MaintenanceStatus::from($request->validated('status')),
                $request->validated(),
            );
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The maintenance status has been updated.')]);

        return back();
    }
}
