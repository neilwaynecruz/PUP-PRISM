<?php

namespace App\Enums;

enum MaintenanceType: string
{
    case Preventive = 'preventive';
    case Corrective = 'corrective';
    case Inspection = 'inspection';
    case Repair = 'repair';
    case Other = 'other';
}
