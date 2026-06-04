<?php

namespace App\Policies;

use App\Models\RequisitionTemplate;
use App\Models\User;

class RequisitionTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Supply Head', 'Property Custodian']);
    }

    public function view(User $user, RequisitionTemplate $requisitionTemplate): bool
    {
        return $user->id === $requisitionTemplate->user_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Supply Head', 'Property Custodian']);
    }

    public function update(User $user, RequisitionTemplate $requisitionTemplate): bool
    {
        return $user->id === $requisitionTemplate->user_id;
    }

    public function delete(User $user, RequisitionTemplate $requisitionTemplate): bool
    {
        return $user->id === $requisitionTemplate->user_id;
    }

    public function duplicate(User $user, RequisitionTemplate $requisitionTemplate): bool
    {
        return $user->id === $requisitionTemplate->user_id;
    }
}
