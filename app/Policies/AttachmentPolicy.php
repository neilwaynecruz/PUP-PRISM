<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AttachmentPolicy
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
    public function view(User $user, Attachment $attachment): bool
    {
        $attachable = $attachment->attachable;

        return $attachable instanceof Model && $user->can('view', $attachable);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Model $attachable): bool
    {
        return $user->can('view', $attachable)
            && $user->hasAnyRole(['Admin', 'Supply Head', 'Property Custodian']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Attachment $attachment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        return $user->hasRole('Admin') || $attachment->uploaded_by === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Attachment $attachment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Attachment $attachment): bool
    {
        return false;
    }
}
