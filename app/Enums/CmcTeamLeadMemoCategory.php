<?php

namespace App\Enums;

enum CmcTeamLeadMemoCategory: string
{
    case PendingStaffAssignment = 'pending_cmc_staff_assignment';
    case ProcessedByStaff = 'processed_by_cmc_staff';

    public function label(): string
    {
        return match ($this) {
            self::PendingStaffAssignment => 'SDMs Pending for CMC Staff Assignment',
            self::ProcessedByStaff => 'SDMs Processed by CMC Staff',
        };
    }
}
