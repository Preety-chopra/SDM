<?php

namespace App\Enums;

enum CmcVerificationSdmTab: string
{
    case Pending = 'pending';
    case Processed = 'processed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending SDMs',
            self::Processed => 'Processed SDMs',
        };
    }
}
