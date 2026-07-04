<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOriginRequest;
use App\Http\Requests\Admin\UpdateOriginRequest;
use App\Models\Origin;
use App\Services\AuditLogService;
use App\Services\MasterData\MasterDataUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OriginController extends Controller
{
    public function __construct(
        private readonly MasterDataUsageService $usage,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Origin::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'active' => $request->has('active') ? $request->boolean('active') : null,
        ];

        $origins = Origin::query()
            ->withCount('products')
            ->when($filters['search'] !== '', fn ($query) => $query->where('name', 'like', "%{$filters['search']}%"))
            ->when($filters['active'] !== null, fn ($query) => $query->where('is_active', $filters['active']))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Origin $origin): array => [
                'id' => $origin->id,
                'name' => $origin->name,
                'is_active' => (bool) $origin->is_active,
                'products_count' => (int) $origin->products_count,
            ]);

        return Inertia::render('admin/origins/Index', [
            'filters' => $filters,
            'origins' => $origins,
            'can' => [
                'create' => $request->user()?->can('create', Origin::class) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Origin::class);

        return Inertia::render('admin/origins/Create');
    }

    public function store(StoreOriginRequest $request): RedirectResponse
    {
        $origin = Origin::query()->create($request->validated());

        AuditLogService::logCreated($origin, "Origin {$origin->name} created.");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Origin created.')]);

        return to_route('admin.origins.edit', $origin);
    }

    public function edit(Origin $origin): Response
    {
        $this->authorize('update', $origin);

        $counts = $this->usage->referenceCounts($origin);

        return Inertia::render('admin/origins/Edit', [
            'origin' => [
                'id' => $origin->id,
                'name' => $origin->name,
                'is_active' => (bool) $origin->is_active,
            ],
            'usage' => $this->usage->presentCounts($counts),
            'impactWarnings' => $this->usage->referenceWarnings($origin),
            'can' => [
                'delete' => $this->usage->canDeleteReference($origin),
                'deactivate' => $origin->is_active,
            ],
        ]);
    }

    public function update(UpdateOriginRequest $request, Origin $origin): RedirectResponse
    {
        $oldValues = $origin->only(['name', 'is_active']);
        $origin->update($request->validated());

        AuditLogService::logUpdated($origin, $oldValues, "Origin {$origin->name} updated.");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Origin updated.')]);

        return to_route('admin.origins.edit', $origin);
    }

    public function destroy(Origin $origin): RedirectResponse
    {
        $this->authorize('delete', $origin);

        if (! $this->usage->canDeleteReference($origin)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This origin is used by products. Deactivate it instead.'),
            ]);

            return back();
        }

        AuditLogService::logDeleted($origin, "Origin {$origin->name} deleted.");
        $origin->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Origin deleted.')]);

        return to_route('admin.origins.index');
    }

    public function deactivate(Origin $origin): RedirectResponse
    {
        $this->authorize('deactivate', $origin);

        if (! $origin->is_active) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This origin is already inactive.')]);

            return back();
        }

        $oldValues = $origin->only(['name', 'is_active']);
        $origin->update(['is_active' => false]);

        AuditLogService::logUpdated($origin, $oldValues, "Origin {$origin->name} deactivated.");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Origin deactivated.')]);

        return to_route('admin.origins.edit', $origin);
    }
}
