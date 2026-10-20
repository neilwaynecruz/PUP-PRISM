<?php

namespace App\Enums;

enum MaintenanceStatus: string
{
    case Reported = 'reported';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
