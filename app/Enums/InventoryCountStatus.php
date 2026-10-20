<?php

namespace App\Enums;

enum InventoryCountStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
