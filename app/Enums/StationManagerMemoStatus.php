<?php

namespace App\Enums;

enum StationManagerMemoStatus: string
{
    case DraftsOwn = 'drafts_own';
    case SubmittedToMe = 'submitted_to_me';
    case PendingAtStationManager = 'pending_at_sm';
    case SubmittedToAssistantManager = 'submitted_to_am';
    case ProcessedByAssistantManager = 'processed_by_am';
    case CancelledByAssistantManager = 'cancelled_by_am';

    public function label(): string
    {
        return match ($this) {
            self::DraftsOwn => 'Drafts (own)',
            self::SubmittedToMe => 'Submitted to me',
            self::PendingAtStationManager => 'Pending (at SM level)',
            self::SubmittedToAssistantManager => 'Submitted to AM',
            self::ProcessedByAssistantManager => 'Processed by AM',
            self::CancelledByAssistantManager => 'Cancelled by AM',
        };
    }
}
