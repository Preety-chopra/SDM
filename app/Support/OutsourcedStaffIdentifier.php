<?php

namespace App\Support;

final class OutsourcedStaffIdentifier
{
    public static function aadhaar(string $identifier): ?string
    {
        $number = preg_replace('/[\s-]+/u', '', $identifier) ?? $identifier;

        return preg_match('/^[0-9]{12}$/D', $number) === 1 ? $number : null;
    }

    public static function hash(?string $aadhaar): ?string
    {
        return $aadhaar === null ? null : hash_hmac('sha256', 'outsourced-staff-aadhaar:'.$aadhaar, config('app.key'));
    }

    public static function mask(string $identifier): string
    {
        $aadhaar = self::aadhaar($identifier);

        return $aadhaar === null ? $identifier : 'XXXX XXXX '.substr($aadhaar, -4);
    }
}
