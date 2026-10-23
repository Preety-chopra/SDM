<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case Station = 'station';
    case StationManager = 'station_manager';
    case AssistantManager = 'assistant_manager';
    case ManagerOperations = 'manager_operations';
    case CmcTeamLead = 'cmc_team_lead';
    case CmcStaff = 'cmc_staff';
    case DeputyGeneralManager = 'deputy_general_manager';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Station => 'Station',
            self::StationManager => 'Station Manager',
            self::AssistantManager => 'Manager/Assistant Manager (O)-Line',
            self::ManagerOperations => 'Manager CMC',
            self::CmcTeamLead => 'CMC SM',
            self::CmcStaff => 'CMC Staff',
            self::DeputyGeneralManager => 'DGM/CMC',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Administrator => 'Administrators',
            self::Station => 'Station Users',
            self::StationManager => 'Station Managers',
            self::AssistantManager => 'Managers/Assistant Managers (O)-Line',
            self::ManagerOperations => 'Manager CMC Users',
            self::CmcTeamLead => 'CMC SMs',
            self::CmcStaff => 'CMC Staff Users',
            self::DeputyGeneralManager => 'DGM/CMC Users',
        };
    }

    public function requiresStation(): bool
    {
        return $this === self::Station;
    }

    public function requiresEmployeeNumber(): bool
    {
        return in_array($this, [self::StationManager, self::AssistantManager, self::ManagerOperations, self::CmcTeamLead, self::CmcStaff, self::DeputyGeneralManager], true);
    }

    public function isManaged(): bool
    {
        return $this !== self::Administrator;
    }

    /** @return list<self> */
    public static function managed(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role): bool => $role->isManaged()));
    }

    /** @return list<string> */
    public static function managedValues(): array
    {
        return array_map(fn (self $role): string => $role->value, self::managed());
    }

    public static function managedFrom(string $value): self
    {
        $role = self::tryFrom($value);

        abort_unless($role?->isManaged(), 404);

        return $role;
    }
}
