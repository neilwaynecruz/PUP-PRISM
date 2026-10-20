<?php

namespace App\Enums;

enum InventoryCountReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case RecountRequested = 'recount_requested';
    case Rejected = 'rejected';
}
