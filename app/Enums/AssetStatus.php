<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Available = 'Available';
    case CheckedOut = 'Checked_Out';
    case Unserviceable = 'Unserviceable';
    case Condemned = 'Condemned';
}
