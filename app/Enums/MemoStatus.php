<?php

namespace App\Enums;

enum MemoStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case AwaitingStationManager = 'awaiting_station_manager';
    case LineReview = 'line_review';
    case AmCmcReview = 'am_cmc_review';
    case CmcAssignment = 'cmc_assignment';
    case CmcScrutiny = 'cmc_scrutiny';
    case DgmApproval = 'dgm_approval';
    case PendingPenaltyLetter = 'pending_penalty_letter';
    case ObjectionWindow = 'objection_window';
    case ObjectionReview = 'objection_review';
    case PendingDeduction = 'pending_deduction';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
