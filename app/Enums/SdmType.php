<?php

namespace App\Enums;

enum SdmType: string
{
    case Cfs = 'customer_facilitation';
    case Ticketing = 'token_vending';

    public function code(): string
    {
        return match ($this) {
            self::Cfs => 'CFS',
            self::Ticketing => 'TICKETING',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Cfs => 'Customer Facilitation Services',
            self::Ticketing => 'Ticketing / Token Vending Services',
        };
    }
}
