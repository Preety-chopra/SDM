<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\IpUtils;

final class ClientLocationService
{
    public function normalizeIp(?string $ip): ?string
    {
        if ($ip === null || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        $packed = inet_pton($ip);
        // Store IPv4-mapped IPv6 addresses in familiar dotted IPv4 form.
        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            return inet_ntop(substr($packed, 12));
        }

        return inet_ntop($packed);
    }

    public function locate(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        if (IpUtils::checkIp($ip, ['127.0.0.0/8', '::1'])) {
            return 'Localhost';
        }

        if (IpUtils::checkIp($ip, ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '169.254.0.0/16', 'fc00::/7', 'fe80::/10'])) {
            return 'Local / private network';
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)) {
            return 'Reserved network';
        }

        if (! config('services.ip_location.enabled')) {
            return null;
        }

        $key = 'activity-location:'.hash('sha256', $ip);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached['location'];
        }

        $location = $this->lookup($ip);
        // Cache failures briefly too, so an outage does not delay every activity.
        Cache::put($key, ['location' => $location], $location === null ? 300 : 86400);

        return $location;
    }

    private function lookup(string $ip): ?string
    {
        try {
            $response = Http::acceptJson()->connectTimeout(1)->timeout(2)
                ->get('https://ipwho.is/'.$ip, ['fields' => 'success,city,region,country']);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            return null;
        }

        $parts = collect(['city', 'region', 'country'])
            ->map(fn (string $field) => $response->json($field))
            ->filter(fn ($part) => is_string($part) && trim($part) !== '')
            ->map(fn (string $part) => trim($part))
            ->unique()->implode(', ');

        return $parts === '' ? null : mb_substr($parts, 0, 255);
    }
}
