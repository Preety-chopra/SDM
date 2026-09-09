<?php

namespace App\Enums;

enum SdmType: string
{
    case Cfs = 'customer_facilitation';
    case Ticketing = 'token_vending';

    public function code(): string
    {
        return match ($this) {
            self::Cfs => 'CFA',
            self::Ticketing => 'TVS',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Cfs => 'Customer Facilitation Services (CFA)',
            self::Ticketing => 'Ticket Vending Services (TVS)',
        };
    }
}
