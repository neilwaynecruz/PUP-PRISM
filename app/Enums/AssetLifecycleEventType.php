<?php

namespace App\Enums;

enum AssetLifecycleEventType: string
{
    case Acquisition = 'acquisition';
    case ConditionChange = 'condition_change';
    case Retirement = 'retirement';
    case Disposal = 'disposal';
}
