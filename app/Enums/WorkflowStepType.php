<?php

namespace App\Enums;

enum WorkflowStepType: string
{
    case StationManager = 'station_manager_signoff';
    case LineReview = 'line_penalty_review';
    case AmCmcToScrutiny = 'am_cmc_to_scrutiny';
    case CmcAssignment = 'cmc_assignment';
    case CmcAssessment = 'cmc_assessment';
    case AmCmcToDgm = 'am_cmc_to_dgm';
    case DgmApproval = 'dgm_cmc_approval';

    public function label(): string
    {
        return match ($this) {
            self::StationManager => 'Station Manager sign-off',
            self::LineReview => 'Manager/Assistant Manager-Line review',
            self::AmCmcToScrutiny => 'AM/CMC forwarding for scrutiny',
            self::CmcAssignment => 'CMC Team Lead staff assignment',
            self::CmcAssessment => 'CMC Staff scrutiny and assessment',
            self::AmCmcToDgm => 'AM/CMC forwarding to DGM/CMC',
            self::DgmApproval => 'DGM/CMC approval',
        };
    }

    public function role(): UserRole
    {
        return match ($this) {
            self::StationManager => UserRole::StationManager,
            self::LineReview => UserRole::AssistantManager,
            self::AmCmcToScrutiny, self::AmCmcToDgm => UserRole::ManagerOperations,
            self::CmcAssignment => UserRole::CmcTeamLead,
            self::CmcAssessment => UserRole::CmcStaff,
            self::DgmApproval => UserRole::DeputyGeneralManager,
        };
    }
}
