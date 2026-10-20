<?php

namespace App\Policies;

use App\Models\AssetTransfer;
use App\Models\User;

class AssetTransferPolicy
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
    public function view(User $user, AssetTransfer $assetTransfer): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Property Custodian']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->hasRole('Admin') || $assetTransfer->initiated_by === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AssetTransfer $assetTransfer): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AssetTransfer $assetTransfer): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AssetTransfer $assetTransfer): bool
    {
        return false;
    }

    public function approve(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->hasRole('Admin') && $assetTransfer->initiated_by !== $user->id;
    }

    public function cancel(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->hasRole('Admin') || $assetTransfer->initiated_by === $user->id;
    }
}
