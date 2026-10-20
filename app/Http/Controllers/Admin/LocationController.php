<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LocationStoreRequest;
use App\Http\Requests\Admin\LocationUpdateRequest;
use App\Models\Department;
use App\Models\Location;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Location::class);

        return Inertia::render('admin/Locations/Index', [
            'locations' => Location::query()->with('department:id,name')->withCount('assets')->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(LocationStoreRequest $request): RedirectResponse
    {
        $location = Location::create($request->validated());
        AuditLogService::logCreated($location);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('The location has been created.')]);

        return back();
    }

    public function update(LocationUpdateRequest $request, Location $location): RedirectResponse
    {
        $oldValues = $location->toArray();
        $location->update($request->validated());
        AuditLogService::logUpdated($location, $oldValues);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('The location has been updated.')]);

        return back();
    }

    public function destroy(Location $location): RedirectResponse
    {
        $this->authorize('update', $location);
        $oldValues = $location->toArray();
        $location->update(['is_active' => false]);
        AuditLogService::logUpdated($location, $oldValues, "Location {$location->code} deactivated.");
        Inertia::flash('toast', ['type' => 'success', 'message' => __('The location has been deactivated. Existing history is preserved.')]);

        return back();
    }
}
