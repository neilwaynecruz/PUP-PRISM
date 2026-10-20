<?php

namespace App\Enums;

enum AssetLifecycleStatus: string
{
    case Active = 'active';
    case Retired = 'retired';
    case Disposed = 'disposed';
}
