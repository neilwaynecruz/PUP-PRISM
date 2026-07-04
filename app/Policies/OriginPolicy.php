<?php

namespace App\Policies;

use App\Models\Origin;
use App\Models\User;

class OriginPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function view(User $user, Origin $origin): bool
    {
        return $user->hasRole('Admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, Origin $origin): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, Origin $origin): bool
    {
        return $user->hasRole('Admin');
    }

    public function deactivate(User $user, Origin $origin): bool
    {
        return $user->hasRole('Admin');
    }
}
