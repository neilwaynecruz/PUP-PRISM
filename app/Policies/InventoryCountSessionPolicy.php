<?php

namespace App\Policies;

use App\Models\InventoryCountSession;
use App\Models\User;

class InventoryCountSessionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Supply Head', 'Property Custodian']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InventoryCountSession $inventoryCountSession): bool
    {
        return $user->hasRole('Admin')
            || $inventoryCountSession->created_by === $user->id
            || $inventoryCountSession->assigned_to === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Supply Head', 'Property Custodian']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InventoryCountSession $inventoryCountSession): bool
    {
        return $inventoryCountSession->assigned_to === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InventoryCountSession $inventoryCountSession): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, InventoryCountSession $inventoryCountSession): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, InventoryCountSession $inventoryCountSession): bool
    {
        return false;
    }

    public function submit(User $user, InventoryCountSession $inventoryCountSession): bool
    {
        return $this->update($user, $inventoryCountSession);
    }

    public function review(User $user, InventoryCountSession $inventoryCountSession): bool
    {
        return $user->hasRole('Admin');
    }

    public function complete(User $user, InventoryCountSession $inventoryCountSession): bool
    {
        return $user->hasRole('Admin');
    }

    public function cancel(User $user, InventoryCountSession $inventoryCountSession): bool
    {
        return $user->hasRole('Admin') || $inventoryCountSession->created_by === $user->id;
    }
}
