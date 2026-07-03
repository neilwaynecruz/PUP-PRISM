<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Position;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'role' => $request->string('role')->trim()->toString(),
            'active' => $request->has('active') ? $request->boolean('active') : null,
        ];

        $users = User::query()
            ->with(['roles:id,name', 'position.department:id,name'])
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $query->where(function ($nestedQuery) use ($filters) {
                    $nestedQuery
                        ->where('name', 'like', "%{$filters['search']}%")
                        ->orWhere('email', 'like', "%{$filters['search']}%")
                        ->orWhereHas('roles', function ($roleQuery) use ($filters) {
                            $roleQuery->where('name', 'like', "%{$filters['search']}%");
                        });
                });
            })
            ->when($filters['role'] !== '', function ($query) use ($filters) {
                $query->whereHas('roles', function ($roleQuery) use ($filters) {
                    $roleQuery->where('name', $filters['role']);
                });
            })
            ->when($filters['active'] !== null, fn ($query) => $query->where('is_active', $filters['active']))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (User $managedUser) => $this->userListItem($managedUser));

        return Inertia::render('admin/users/Index', [
            'filters' => $filters,
            'users' => $users,
            'roles' => $this->availableRoles(),
            'can' => [
                'create' => $request->user()?->can('create', User::class) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('admin/users/Create', [
            'roles' => $this->availableRoles(),
            'positions' => $this->positionOptions(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();

        $managedUser = DB::transaction(function () use ($validated): User {
            $managedUser = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'position_id' => $validated['position_id'] ?? null,
                'email_verified_at' => now(),
                'invited_at' => now(),
                'is_active' => true,
            ]);

            $managedUser->syncRoles([$validated['role']]);
            $managedUser->load(['roles:id,name', 'position.department:id,name']);

            AuditLogService::log(
                'create',
                "User {$managedUser->name} created.",
                $managedUser,
                null,
                $this->auditPayload($managedUser),
            );

            return $managedUser;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User account created.')]);

        return to_route('admin.users.edit', ['managedUser' => $managedUser]);
    }

    public function edit(Request $request, User $managedUser): Response
    {
        $this->authorize('update', $managedUser);

        $managedUser->load(['roles:id,name', 'position.department:id,name']);

        return Inertia::render('admin/users/Edit', [
            'user' => $this->userDetail($managedUser),
            'roles' => $this->availableRoles(),
            'positions' => $this->positionOptions(),
            'can' => [
                'deactivate' => $this->canDeactivate($request->user(), $managedUser),
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $managedUser): RedirectResponse
    {
        $this->authorize('update', $managedUser);

        $validated = $request->validated();

        if ($this->wouldRemoveLastActiveAdmin($managedUser, $validated['role'])) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('At least one active Admin account must remain assigned.'),
            ]);

            return back();
        }

        DB::transaction(function () use ($managedUser, $validated): void {
            $managedUser->load(['roles:id,name', 'position.department:id,name']);

            $oldValues = $this->auditPayload($managedUser);

            $managedUser->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'position_id' => $validated['position_id'] ?? null,
                'email_verified_at' => now(),
            ]);

            $managedUser->save();
            $managedUser->syncRoles([$validated['role']]);
            $managedUser->load(['roles:id,name', 'position.department:id,name']);

            AuditLogService::log(
                'update',
                "User {$managedUser->name} updated.",
                $managedUser,
                $oldValues,
                $this->auditPayload($managedUser),
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User account updated.')]);

        return to_route('admin.users.edit', ['managedUser' => $managedUser]);
    }

    public function deactivate(Request $request, User $managedUser): RedirectResponse
    {
        $this->authorize('deactivate', $managedUser);

        if (! $request->user() instanceof User) {
            abort(403);
        }

        if ($request->user()->is($managedUser)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('You cannot deactivate your own account.'),
            ]);

            return back();
        }

        if (! $managedUser->is_active) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This user account is already inactive.'),
            ]);

            return back();
        }

        if ($this->isLastActiveAdmin($managedUser)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('You cannot deactivate the last active Admin account.'),
            ]);

            return back();
        }

        DB::transaction(function () use ($managedUser): void {
            $managedUser->load(['roles:id,name', 'position.department:id,name']);

            $oldValues = $this->auditPayload($managedUser);

            $managedUser->forceFill([
                'is_active' => false,
            ])->save();

            $managedUser->load(['roles:id,name', 'position.department:id,name']);

            AuditLogService::log(
                'deactivate',
                "User {$managedUser->name} deactivated.",
                $managedUser,
                $oldValues,
                $this->auditPayload($managedUser),
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User account deactivated.')]);

        return to_route('admin.users.index');
    }

    /**
     * @return array<int, string>
     */
    private function availableRoles(): array
    {
        return ['Admin', 'Supply Head', 'Property Custodian'];
    }

    /**
     * @return array{id: int, name: string, email: string, role: string|null, position: string|null, is_active: bool, invited_at: string|null}
     */
    private function userListItem(User $managedUser): array
    {
        return [
            'id' => (int) $managedUser->id,
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'role' => $this->primaryRole($managedUser),
            'position' => $this->positionLabel($managedUser),
            'is_active' => (bool) $managedUser->is_active,
            'invited_at' => $managedUser->invited_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string, email: string, role: string|null, position_id: int|null, position: string|null, is_active: bool, invited_at: string|null, is_self: bool, is_last_active_admin: bool}
     */
    private function userDetail(User $managedUser): array
    {
        return [
            ...$this->userListItem($managedUser),
            'position_id' => $managedUser->position_id !== null ? (int) $managedUser->position_id : null,
            'is_self' => request()->user()?->is($managedUser) ?? false,
            'is_last_active_admin' => $this->isLastActiveAdmin($managedUser),
        ];
    }

    /**
     * @return array<int, array{id: int, title: string, code: string, department: string|null, is_active: bool, label: string}>
     */
    private function positionOptions(): array
    {
        return Position::query()
            ->with('department:id,name')
            ->orderBy('title')
            ->get()
            ->map(function (Position $position): array {
                $label = "{$position->title} ({$position->code})";

                if ($position->department?->name) {
                    $label .= " - {$position->department->name}";
                }

                if (! $position->is_active) {
                    $label .= ' [Inactive]';
                }

                return [
                    'id' => (int) $position->id,
                    'title' => $position->title,
                    'code' => $position->code,
                    'department' => $position->department?->name,
                    'is_active' => (bool) $position->is_active,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function auditPayload(User $managedUser): array
    {
        return [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'role' => $this->primaryRole($managedUser),
            'position_id' => $managedUser->position_id,
            'position' => $this->positionLabel($managedUser),
            'is_active' => (bool) $managedUser->is_active,
            'invited_at' => $managedUser->invited_at?->toIso8601String(),
            'email_verified_at' => $managedUser->email_verified_at?->toIso8601String(),
        ];
    }

    private function primaryRole(User $managedUser): ?string
    {
        return $managedUser->relationLoaded('roles')
            ? $managedUser->roles->pluck('name')->first()
            : $managedUser->getRoleNames()->first();
    }

    private function positionLabel(User $managedUser): ?string
    {
        if (! $managedUser->relationLoaded('position') || ! $managedUser->position) {
            return null;
        }

        $label = "{$managedUser->position->title} ({$managedUser->position->code})";

        if ($managedUser->position->relationLoaded('department') && $managedUser->position->department?->name) {
            $label .= " - {$managedUser->position->department->name}";
        }

        return $label;
    }

    private function canDeactivate(?User $actor, User $managedUser): bool
    {
        if (! $actor instanceof User) {
            return false;
        }

        if (! $actor->can('deactivate', $managedUser)) {
            return false;
        }

        if (! $managedUser->is_active) {
            return false;
        }

        if ($actor->is($managedUser)) {
            return false;
        }

        return ! $this->isLastActiveAdmin($managedUser);
    }

    private function isLastActiveAdmin(User $managedUser): bool
    {
        if (! $managedUser->is_active || ! $managedUser->hasRole('Admin')) {
            return false;
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('name', 'Admin'))
            ->count() === 1;
    }

    private function wouldRemoveLastActiveAdmin(User $managedUser, string $newRole): bool
    {
        return $managedUser->is_active
            && $managedUser->hasRole('Admin')
            && $newRole !== 'Admin'
            && $this->isLastActiveAdmin($managedUser);
    }
}
