<?php

namespace App\Enums;

enum LocationType: string
{
    case Classroom = 'classroom';
    case Laboratory = 'laboratory';
    case AdministrativeOffice = 'administrative_office';
    case FacultyOffice = 'faculty_office';
    case ConferenceRoom = 'conference_room';
    case Library = 'library';
    case AudioVisualRoom = 'audio_visual_room';
    case MultipurposeHall = 'multipurpose_hall';
    case MiniHall = 'mini_hall';
    case Gymnasium = 'gymnasium';
    case Stockroom = 'stockroom';
    case StorageArea = 'storage_area';
    case Workshop = 'workshop';
    case ServerRoom = 'server_room';

    public function label(): string
    {
        return match ($this) {
            self::Classroom => 'Classroom',
            self::Laboratory => 'Laboratory',
            self::AdministrativeOffice => 'Administrative Office',
            self::FacultyOffice => 'Faculty Office',
            self::ConferenceRoom => 'Conference Room',
            self::Library => 'Library',
            self::AudioVisualRoom => 'Audio Visual Room',
            self::MultipurposeHall => 'Multipurpose Hall',
            self::MiniHall => 'Mini Hall',
            self::Gymnasium => 'Gymnasium',
            self::Stockroom => 'Stockroom',
            self::StorageArea => 'Storage Area',
            self::Workshop => 'Workshop',
            self::ServerRoom => 'Server Room',
        };
    }

    /**
     * Facility groups this room or facility type may be classified under. Support spaces
     * (stockrooms, storage areas, workshops, server rooms) can sit inside any group.
     *
     * @return array<int, FacilityGroup>
     */
    public function facilityGroups(): array
    {
        return match ($this) {
            self::Classroom, self::Laboratory => [FacilityGroup::Academic],
            self::AdministrativeOffice, self::FacultyOffice, self::ConferenceRoom, self::Library => [FacilityGroup::Administrative],
            self::AudioVisualRoom, self::MultipurposeHall, self::MiniHall => [FacilityGroup::Events],
            self::Gymnasium => [FacilityGroup::Sports],
            self::Stockroom, self::StorageArea, self::Workshop, self::ServerRoom => FacilityGroup::cases(),
        };
    }

    public function belongsToFacilityGroup(FacilityGroup $group): bool
    {
        return in_array($group, $this->facilityGroups(), true);
    }
}
