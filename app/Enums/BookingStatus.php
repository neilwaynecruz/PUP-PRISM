<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Requested = 'Requested';
    case Approved = 'Approved';
    case Rejected = 'Rejected';
    case Cancelled = 'Cancelled';
}
