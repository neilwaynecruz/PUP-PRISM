<?php

namespace App\Enums;

enum FacilityGroup: string
{
    case Academic = 'academic';
    case Administrative = 'administrative';
    case Sports = 'sports';
    case Events = 'events';

    public function label(): string
    {
        return match ($this) {
            self::Academic => 'Academic',
            self::Administrative => 'Administrative',
            self::Sports => 'Sports',
            self::Events => 'Events',
        };
    }
}
