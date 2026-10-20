<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\AssetMaintenance;
use App\Models\AssetTransfer;
use App\Models\Location;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Asset::class);
        $search = $request->string('search')->trim()->toString();
        $locationId = $request->integer('location_id') ?: null;
        $lifecycleStatus = $request->string('lifecycle_status')->trim()->toString();

        $assets = Asset::query()
            ->with(['product:id,name,sku', 'position:id,title,department_id', 'position.department:id,name', 'currentLocation:id,code,name'])
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query->where('tag_code', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")),
            ))
            ->when($locationId, fn ($query) => $query->where('current_location_id', $locationId))
            ->when($lifecycleStatus !== '', fn ($query) => $query->where('lifecycle_status', $lifecycleStatus))
            ->orderBy('tag_code')
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('inventory/assets/Index', [
            'assets' => $assets,
            'filters' => ['search' => $search, 'location_id' => $locationId, 'lifecycle_status' => $lifecycleStatus],
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function show(Asset $asset): Response
    {
        $this->authorize('view', $asset);
        $asset->load([
            'product:id,name,sku',
            'position:id,title,code,department_id',
            'position.department:id,name',
            'currentLocation:id,code,name,building,room',
            'maintenances' => fn ($query) => $query->with(['reporter:id,name', 'assignee:id,name'])->latest(),
            'transfers' => fn ($query) => $query->with(['fromLocation:id,code,name', 'toLocation:id,code,name', 'initiator:id,name', 'approver:id,name'])->latest(),
            'lifecycleEvents' => fn ($query) => $query->with(['initiator:id,name', 'approver:id,name'])->latest(),
            'attachments' => fn ($query) => $query->with('uploader:id,name')->latest(),
        ]);

        return Inertia::render('inventory/assets/Show', [
            'asset' => $asset,
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'positions' => Position::query()->where('is_active', true)->with('department:id,name')->orderBy('title')->get(['id', 'department_id', 'title', 'code']),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'can' => [
                'createMaintenance' => request()->user()->can('create', AssetMaintenance::class),
                'createTransfer' => request()->user()->can('create', AssetTransfer::class),
                'createLifecycle' => request()->user()->can('create', AssetLifecycleEvent::class),
            ],
        ]);
    }
}
