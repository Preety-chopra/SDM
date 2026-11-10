<?php

namespace App\Enums;

enum WorkflowStepType: string
{
    case StationManager = 'station_manager_signoff';
    case LineReview = 'line_penalty_review';
    case AmCmcToScrutiny = 'am_cmc_to_scrutiny';
    case CmcAssignment = 'cmc_assignment';
    case CmcAssessment = 'cmc_assessment';
    case CmcVerification = 'cmc_verification';
    case AmCmcToDgm = 'am_cmc_to_dgm';
    case DgmApproval = 'dgm_cmc_approval';
    case DgmAftReview = 'dgm_aft_review';
    case DgmCmcToReview = 'dgm_cmc_to_review';

    public function label(): string
    {
        return match ($this) {
            self::StationManager => 'Station Manager review',
            self::LineReview => 'Assistant Manager/Manager(Line) review',
            self::AmCmcToScrutiny => 'Manager CMC forwarding for scrutiny',
            self::CmcAssignment => 'CMC SM- staff assignment',
            self::CmcAssessment => 'CMC Staff scrutiny and assessment',
            self::CmcVerification => 'CMC SM verification',
            self::AmCmcToDgm => 'Manager CMC forwarding to DGM/CMC',
            self::DgmApproval => 'DGM/CMC approval',
            self::DgmAftReview => 'DGM AFT inspection report review',
            self::DgmCmcToReview => 'DGM/CMC forwarding inspection report to AM CMC',
        };
    }

    public function role(): UserRole
    {
        return match ($this) {
            self::StationManager => UserRole::StationManager,
            self::LineReview => UserRole::AssistantManager,
            self::AmCmcToScrutiny, self::AmCmcToDgm => UserRole::ManagerOperations,
            self::CmcAssignment, self::CmcVerification => UserRole::CmcTeamLead,
            self::CmcAssessment => UserRole::CmcStaff,
            self::DgmApproval, self::DgmCmcToReview => UserRole::DeputyGeneralManager,
            self::DgmAftReview => UserRole::DgmAft,
        };
    }

    /** @return list<self> */
    public static function generalRoute(): array
    {
        return [self::StationManager, self::LineReview, self::AmCmcToScrutiny, self::CmcAssignment,
            self::CmcAssessment, self::CmcVerification, self::AmCmcToDgm, self::DgmApproval];
    }

    /** @return list<self> */
    public static function aftRoute(): array
    {
        return [self::DgmAftReview, self::DgmCmcToReview, self::AmCmcToScrutiny, self::CmcAssignment,
            self::CmcAssessment, self::CmcVerification, self::AmCmcToDgm, self::DgmApproval];
    }
}
