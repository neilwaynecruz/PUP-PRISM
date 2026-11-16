<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function view(User $user, Category $_category): bool
    {
        return $user->hasRole('Admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, Category $_category): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, Category $_category): bool
    {
        return $user->hasRole('Admin');
    }

    public function deactivate(User $user, Category $_category): bool
    {
        return $user->hasRole('Admin');
    }
}
