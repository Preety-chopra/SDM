<?php

namespace App\Enums;

enum WorkflowStage: string
{
    case StationManager = 'station_manager';
    case LineManager = 'manager_assistant_manager_line';
    case OperationsManager = 'manager_operations';
    case DeputyGeneralManager = 'dgm_operations';

    public function label(): string
    {
        return match ($this) {
            self::StationManager => 'Station Manager',
            self::LineManager => 'Manager/Assistant Manager (O)-Line',
            self::OperationsManager => 'Manager CMC',
            self::DeputyGeneralManager => 'DGM/O',
        };
    }

    public function levelLabel(): string
    {
        return match ($this) {
            self::StationManager => 'SM level',
            self::LineManager => 'AM level',
            self::OperationsManager => 'Manager level',
            self::DeputyGeneralManager => 'DGM level',
        };
    }

    public function role(): UserRole
    {
        return match ($this) {
            self::StationManager => UserRole::StationManager,
            self::LineManager => UserRole::AssistantManager,
            self::OperationsManager => UserRole::ManagerOperations,
            self::DeputyGeneralManager => UserRole::DeputyGeneralManager,
        };
    }

    public function sequence(): int
    {
        return match ($this) {
            self::StationManager => 1,
            self::LineManager => 2,
            self::OperationsManager => 3,
            self::DeputyGeneralManager => 4,
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::StationManager => self::LineManager,
            self::LineManager => self::OperationsManager,
            self::OperationsManager => self::DeputyGeneralManager,
            self::DeputyGeneralManager => null,
        };
    }
}
