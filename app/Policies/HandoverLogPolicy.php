<?php

namespace App\Policies;

use App\Models\HandoverLog;
use App\Models\User;

class HandoverLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Property Custodian']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Property Custodian']);
    }

    public function cancel(User $user, HandoverLog $_handoverLog): bool
    {
        return $user->hasAnyRole(['Admin', 'Property Custodian']);
    }

    public function resend(User $user, HandoverLog $_handoverLog): bool
    {
        return $user->hasAnyRole(['Admin', 'Property Custodian']);
    }

    public function verify(User $user, HandoverLog $handoverLog): bool
    {
        return $user->id === $handoverLog->to_user_id;
    }
}
