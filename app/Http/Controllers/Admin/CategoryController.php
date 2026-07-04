<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\AuditLogService;
use App\Services\MasterData\MasterDataUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(
        private readonly MasterDataUsageService $usage,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Category::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'active' => $request->has('active') ? $request->boolean('active') : null,
        ];

        $categories = Category::query()
            ->withCount('products')
            ->when($filters['search'] !== '', fn ($query) => $query->where('name', 'like', "%{$filters['search']}%"))
            ->when($filters['active'] !== null, fn ($query) => $query->where('is_active', $filters['active']))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'is_active' => (bool) $category->is_active,
                'products_count' => (int) $category->products_count,
            ]);

        return Inertia::render('admin/categories/Index', [
            'filters' => $filters,
            'categories' => $categories,
            'can' => [
                'create' => $request->user()?->can('create', Category::class) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Category::class);

        return Inertia::render('admin/categories/Create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = Category::query()->create($request->validated());

        AuditLogService::logCreated($category, "Category {$category->name} created.");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('admin.categories.edit', $category);
    }

    public function edit(Category $category): Response
    {
        $this->authorize('update', $category);

        $counts = $this->usage->referenceCounts($category);

        return Inertia::render('admin/categories/Edit', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'is_active' => (bool) $category->is_active,
            ],
            'usage' => $this->usage->presentCounts($counts),
            'impactWarnings' => $this->usage->referenceWarnings($category),
            'can' => [
                'delete' => $this->usage->canDeleteReference($category),
                'deactivate' => $category->is_active,
            ],
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $oldValues = $category->only(['name', 'is_active']);
        $category->update($request->validated());

        AuditLogService::logUpdated($category, $oldValues, "Category {$category->name} updated.");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return to_route('admin.categories.edit', $category);
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        if (! $this->usage->canDeleteReference($category)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This category is used by products. Deactivate it instead.'),
            ]);

            return back();
        }

        AuditLogService::logDeleted($category, "Category {$category->name} deleted.");
        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return to_route('admin.categories.index');
    }

    public function deactivate(Category $category): RedirectResponse
    {
        $this->authorize('deactivate', $category);

        if (! $category->is_active) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This category is already inactive.')]);

            return back();
        }

        $oldValues = $category->only(['name', 'is_active']);
        $category->update(['is_active' => false]);

        AuditLogService::logUpdated($category, $oldValues, "Category {$category->name} deactivated.");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deactivated.')]);

        return to_route('admin.categories.edit', $category);
    }
}
