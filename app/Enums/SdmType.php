<?php

namespace App\Enums;

enum SdmType: string
{
    case Cfs = 'customer_facilitation';
    case Ticketing = 'token_vending';
    case WandW = 'watch_and_ward';
    case Marshall = 'marshall';


    public function code(): string
    {
        return match ($this) {
            self::Cfs => 'CFA',
            self::Ticketing => 'TVS',
            self::WandW => 'W&W',
            self::Marshall => 'MAR',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Cfs => 'Customer Facilitation Services (CFA)',
            self::Ticketing => 'Ticket Vending Services (TVS)',
            self::WandW => 'Watch and Ward',
            self::Marshall => 'Marshall',
        };
    }
}
