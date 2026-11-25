<?php

namespace App\Enums;

enum CmcTeamLeadMemoCategory: string
{
    case PendingStaffAssignment = 'pending_cmc_staff_assignment';
    case AssignedToStaff = 'assigned_to_cmc_staff';
    case ProcessedByStaff = 'processed_by_cmc_staff';

    public function label(): string
    {
        return match ($this) {
            self::PendingStaffAssignment => 'SDMs Pending for CMC Staff Assignment',
            self::AssignedToStaff => 'SDMs assigned to CMC staff',
            self::ProcessedByStaff => 'SDMs Processed by CMC Staff',
        };
    }
}
