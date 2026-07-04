<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\AuditLogService;
use App\Services\MasterData\MasterDataUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly MasterDataUsageService $usage,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Department::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'active' => $request->has('active') ? $request->boolean('active') : null,
        ];

        $departments = Department::query()
            ->withCount('positions')
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $query->where(function ($nested) use ($filters): void {
                    $nested
                        ->where('name', 'like', "%{$filters['search']}%")
                        ->orWhere('code', 'like', "%{$filters['search']}%");
                });
            })
            ->when($filters['active'] !== null, fn ($query) => $query->where('is_active', $filters['active']))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'is_active' => (bool) $department->is_active,
                'positions_count' => (int) $department->positions_count,
            ]);

        return Inertia::render('admin/departments/Index', [
            'filters' => $filters,
            'departments' => $departments,
            'can' => [
                'create' => $request->user()?->can('create', Department::class) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Department::class);

        return Inertia::render('admin/departments/Create');
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $department = Department::query()->create($request->validated());

        AuditLogService::logCreated($department, "Department {$department->name} created.");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department created.')]);

        return to_route('admin.departments.edit', $department);
    }

    public function edit(Department $department): Response
    {
        $this->authorize('update', $department);

        $counts = $this->usage->departmentCounts($department);

        return Inertia::render('admin/departments/Edit', [
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'is_active' => (bool) $department->is_active,
            ],
            'usage' => $this->usage->presentCounts($counts),
            'impactWarnings' => $this->usage->departmentWarnings($department),
            'can' => [
                'delete' => $this->usage->canDeleteDepartment($department),
                'deactivate' => $department->is_active,
            ],
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $oldValues = $department->only(['name', 'code', 'is_active']);
        $department->update($request->validated());

        AuditLogService::logUpdated($department, $oldValues, "Department {$department->name} updated.");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department updated.')]);

        return to_route('admin.departments.edit', $department);
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);

        if (! $this->usage->canDeleteDepartment($department)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This department still has positions. Remove or reassign them before deleting, or deactivate instead.'),
            ]);

            return back();
        }

        AuditLogService::logDeleted($department, "Department {$department->name} deleted.");
        $department->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department deleted.')]);

        return to_route('admin.departments.index');
    }

    public function deactivate(Department $department): RedirectResponse
    {
        $this->authorize('deactivate', $department);

        if (! $department->is_active) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This department is already inactive.')]);

            return back();
        }

        $oldValues = $department->only(['name', 'code', 'is_active']);
        $department->update(['is_active' => false]);

        AuditLogService::logUpdated($department, $oldValues, "Department {$department->name} deactivated.");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department deactivated.')]);

        return to_route('admin.departments.edit', $department);
    }
}
