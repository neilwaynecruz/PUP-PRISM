<?php

namespace App\Policies;

use App\Models\Requisition;
use App\Models\User;

class RequisitionPolicy
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
    public function view(User $user, Requisition $requisition): bool
    {
        return $user->hasAnyRole(['Admin', 'Supply Head', 'Property Custodian']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Requisition $requisition): bool
    {
        if ($user->hasAnyRole(['Admin', 'Supply Head'])) {
            return true;
        }

        return $user->id === $requisition->requester_id && $requisition->status === 'Draft';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Requisition $requisition): bool
    {
        return $user->id === $requisition->requester_id && $requisition->status === 'Draft';
    }

    public function approve(User $user, Requisition $requisition): bool
    {
        if (! $user->hasAnyRole(['Admin', 'Supply Head'])) {
            return false;
        }

        if ($user->id === $requisition->requester_id) {
            return false;
        }

        return $requisition->status === 'Submitted';
    }

    public function issue(User $user, Requisition $requisition): bool
    {
        if (! $user->hasAnyRole(['Admin', 'Supply Head'])) {
            return false;
        }

        return $requisition->status === 'Approved';
    }
}
