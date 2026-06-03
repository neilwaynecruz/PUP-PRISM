<?php

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
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
    public function view(User $user, Booking $booking): bool
    {
        return $user->hasAnyRole(['Admin', 'Property Custodian']) || $user->id === $booking->requester_id;
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
    public function update(User $user, Booking $booking): bool
    {
        if ($user->hasAnyRole(['Admin', 'Property Custodian'])) {
            return true;
        }

        return $user->id === $booking->requester_id && $booking->status === BookingStatus::Requested;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Booking $booking): bool
    {
        return $user->id === $booking->requester_id && $booking->status === BookingStatus::Requested;
    }

    public function approve(User $user, Booking $booking): bool
    {
        return $user->hasAnyRole(['Admin', 'Property Custodian']) && $booking->status === BookingStatus::Requested;
    }
}
