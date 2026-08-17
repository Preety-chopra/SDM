<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case Station = 'station';
    case StationManager = 'station_manager';
    case AssistantManager = 'assistant_manager';
    case DeputyGeneralManager = 'deputy_general_manager';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Station => 'Station',
            self::StationManager => 'Station Manager',
            self::AssistantManager => 'Assistant Manager',
            self::DeputyGeneralManager => 'Deputy General Manager',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Administrator => 'Administrators',
            self::Station => 'Station Users',
            self::StationManager => 'Station Managers',
            self::AssistantManager => 'Assistant Managers',
            self::DeputyGeneralManager => 'Deputy General Managers',
        };
    }

    public function requiresStation(): bool
    {
        return in_array($this, [self::Station, self::StationManager], true);
    }

    public function requiresEmployeeNumber(): bool
    {
        return in_array($this, [self::StationManager, self::AssistantManager, self::DeputyGeneralManager], true);
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
