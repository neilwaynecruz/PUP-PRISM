<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;

class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function view(User $user, Position $position): bool
    {
        return $user->hasRole('Admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, Position $position): bool
    {
        return $user->hasRole('Admin');
    }

    public function delete(User $user, Position $position): bool
    {
        return $user->hasRole('Admin');
    }

    public function deactivate(User $user, Position $position): bool
    {
        return $user->hasRole('Admin');
    }
}
