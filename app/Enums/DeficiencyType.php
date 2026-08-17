<?php

namespace App\Enums;

enum DeficiencyType: string
{
    case LateReporting = 'late_reporting';
    case DressCode = 'dress_code_or_instructions';
    case Misbehavior = 'misbehavior';
    case CashCheck = 'cash_check';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::LateReporting => 'Late Reporting',
            self::DressCode => 'Poor Dress Code / Failure to Follow Instructions',
            self::Misbehavior => 'Misbehavior with Customer / Staff',
            self::CashCheck => 'TOM / CCC Inspection / Cash Checking',
            self::Other => 'Other Discrepancy',
        };
    }
}
