<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function view(User $user, Department $_department): bool
    {
        return $user->hasRole('Admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, Department $_department): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, Department $_department): bool
    {
        return $user->hasRole('Admin');
    }

    public function deactivate(User $user, Department $_department): bool
    {
        return $user->hasRole('Admin');
    }
}
