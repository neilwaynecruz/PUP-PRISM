<?php

namespace App\Policies;

use App\Models\InventoryAlert;
use App\Models\User;

class InventoryAlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Supply Head']);
    }

    public function view(User $user, InventoryAlert $inventoryAlert): bool
    {
        return $this->viewAny($user);
    }

    public function acknowledge(User $user, InventoryAlert $inventoryAlert): bool
    {
        return $this->viewAny($user) && $inventoryAlert->isActive();
    }

    public function assign(User $user, InventoryAlert $inventoryAlert): bool
    {
        return $this->viewAny($user) && $inventoryAlert->isActive();
    }

    public function resolve(User $user, InventoryAlert $inventoryAlert): bool
    {
        return $this->viewAny($user) && $inventoryAlert->isActive();
    }
}
