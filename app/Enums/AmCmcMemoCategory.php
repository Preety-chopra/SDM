<?php

namespace App\Enums;

enum AmCmcMemoCategory: string
{
    case ReceivedFromLines = 'received_from_lines';
    case ProcessedByCmc = 'processed_by_cmc';
    case ApprovedByDgmCmc = 'approved_by_dgm_cmc';

    public function label(): string
    {
        return match ($this) {
            self::ReceivedFromLines => 'SDMs Received from Lines',
            self::ProcessedByCmc => 'SDMs Processed by CMC',
            self::ApprovedByDgmCmc => 'SDMs Approved by DGM/CMC',
        };
    }
}
