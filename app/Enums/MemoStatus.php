<?php

namespace App\Enums;

use Illuminate\Support\Str;

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

    public function label(): string
    {
        return match ($this) {
            self::AmCmcReview => 'AM CMC Review',
            self::CmcAssignment => 'CMC Assignment',
            self::CmcScrutiny => 'CMC Scrutiny',
            self::DgmApproval => 'DGM Approval',
            default => Str::headline($this->value),
        };
    }

    /** @return list<self> */
    public static function lineManagerFilterCases(): array
    {
        return [
            self::LineReview,
            self::AmCmcReview,
            self::CmcAssignment,
            self::CmcScrutiny,
            self::DgmApproval,
            self::PendingPenaltyLetter,
            self::ObjectionWindow,
            self::ObjectionReview,
            self::Cancelled,
            self::Closed,
        ];
    }

    public function lineManagerFilterLabel(): string
    {
        return $this === self::LineReview
            ? 'Pending (at current user level)'
            : $this->label();
    }
}
