<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Supply Head', 'Property Custodian']);
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->hasAnyRole(['Admin', 'Supply Head', 'Property Custodian']);
    }
}
