<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePositionRequest;
use App\Http\Requests\Admin\UpdatePositionRequest;
use App\Models\Department;
use App\Models\Position;
use App\Services\AuditLogService;
use App\Services\MasterData\MasterDataUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PositionController extends Controller
{
    public function __construct(
        private readonly MasterDataUsageService $usage,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Position::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'department_id' => $request->integer('department_id'),
            'active' => $request->has('active') ? $request->boolean('active') : null,
        ];

        $positions = Position::query()
            ->with('department:id,name')
            ->withCount(['users', 'assets'])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $query->where(function ($nested) use ($filters): void {
                    $nested
                        ->where('title', 'like', "%{$filters['search']}%")
                        ->orWhere('code', 'like', "%{$filters['search']}%")
                        ->orWhereHas('department', fn ($dept) => $dept->where('name', 'like', "%{$filters['search']}%"));
                });
            })
            ->when($filters['department_id'] > 0, fn ($query) => $query->where('department_id', $filters['department_id']))
            ->when($filters['active'] !== null, fn ($query) => $query->where('is_active', $filters['active']))
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Position $position): array => [
                'id' => $position->id,
                'title' => $position->title,
                'code' => $position->code,
                'is_active' => (bool) $position->is_active,
                'department' => $position->department?->name,
                'users_count' => (int) $position->users_count,
                'assets_count' => (int) $position->assets_count,
            ]);

        return Inertia::render('admin/positions/Index', [
            'filters' => $filters,
            'positions' => $positions,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'can' => [
                'create' => $request->user()?->can('create', Position::class) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Position::class);

        return Inertia::render('admin/positions/Create', [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StorePositionRequest $request): RedirectResponse
    {
        $position = Position::query()->create($request->validated());

        AuditLogService::logCreated($position, "Position {$position->title} created.");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Position created.')]);

        return to_route('admin.positions.edit', $position);
    }

    public function edit(Position $position): Response
    {
        $this->authorize('update', $position);

        $position->load('department:id,name');
        $counts = $this->usage->positionCounts($position);

        return Inertia::render('admin/positions/Edit', [
            'position' => [
                'id' => $position->id,
                'department_id' => $position->department_id,
                'title' => $position->title,
                'code' => $position->code,
                'is_active' => (bool) $position->is_active,
                'department' => $position->department?->name,
            ],
            'departments' => Department::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'usage' => $this->usage->presentCounts($counts),
            'impactWarnings' => $this->usage->positionWarnings($position),
            'can' => [
                'delete' => $this->usage->canDeletePosition($position),
                'deactivate' => $position->is_active && $this->usage->canDeactivatePosition($position),
            ],
        ]);
    }

    public function update(UpdatePositionRequest $request, Position $position): RedirectResponse
    {
        if (! $request->boolean('is_active') && $position->is_active && ! $this->usage->canDeactivatePosition($position)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Active users are still assigned to this position. Reassign them before deactivating.'),
            ]);

            return back()->withInput();
        }

        $oldValues = $position->only(['department_id', 'title', 'code', 'is_active']);
        $position->update($request->validated());

        AuditLogService::logUpdated($position, $oldValues, "Position {$position->title} updated.");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Position updated.')]);

        return to_route('admin.positions.edit', $position);
    }

    public function destroy(Position $position): RedirectResponse
    {
        $this->authorize('delete', $position);

        if (! $this->usage->canDeletePosition($position)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This position is assigned to users or assets. Deactivate it instead.'),
            ]);

            return back();
        }

        AuditLogService::logDeleted($position, "Position {$position->title} deleted.");
        $position->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Position deleted.')]);

        return to_route('admin.positions.index');
    }

    public function deactivate(Position $position): RedirectResponse
    {
        $this->authorize('deactivate', $position);

        if (! $this->usage->canDeactivatePosition($position)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Active users are still assigned to this position.'),
            ]);

            return back();
        }

        if (! $position->is_active) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This position is already inactive.')]);

            return back();
        }

        $oldValues = $position->only(['department_id', 'title', 'code', 'is_active']);
        $position->update(['is_active' => false]);

        AuditLogService::logUpdated($position, $oldValues, "Position {$position->title} deactivated.");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Position deactivated.')]);

        return to_route('admin.positions.edit', $position);
    }
}
