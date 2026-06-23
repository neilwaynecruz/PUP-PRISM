<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Supply Head']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasAnyRole(['Admin', 'Supply Head']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Supply Head']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasAnyRole(['Admin', 'Supply Head']);
    }

    public function send(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasAnyRole(['Admin', 'Supply Head']);
    }

    public function receive(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasAnyRole(['Admin', 'Supply Head']);
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasAnyRole(['Admin', 'Supply Head']);
    }
}
