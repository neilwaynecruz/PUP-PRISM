<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function view(User $user, User $_managedUser): bool
    {
        return $user->hasRole('Admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, User $_managedUser): bool
    {
        return $user->hasRole('Admin');
    }

    public function deactivate(User $user, User $managedUser): bool
    {
        return $user->hasRole('Admin') && ! $user->is($managedUser);
    }

    public function reactivate(User $user, User $managedUser): bool
    {
        return $user->hasRole('Admin') && ! $user->is($managedUser);
    }
}
