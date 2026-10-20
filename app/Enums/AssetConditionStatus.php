<?php

namespace App\Enums;

enum AssetConditionStatus: string
{
    case Serviceable = 'serviceable';
    case Unserviceable = 'unserviceable';
    case Damaged = 'damaged';
}
