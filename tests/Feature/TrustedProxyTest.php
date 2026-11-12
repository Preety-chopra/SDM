<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_guest_redirect_preserves_https_host_and_port_from_the_configured_proxy(): void
    {
        config(['trustedproxy.proxies' => ['127.0.0.1']]);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'X-Forwarded-Host' => 'sdm.dmrc.org',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Port' => '5252',
                'X-Forwarded-Prefix' => '/spoofed',
            ])
            ->get('http://127.0.0.1/dashboard')
            ->assertRedirect('https://sdm.dmrc.org:5252/login');
    }

    public function test_forwarded_headers_from_an_untrusted_client_do_not_change_the_login_redirect(): void
    {
        config(['trustedproxy.proxies' => ['127.0.0.1']]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withHeaders([
                'X-Forwarded-Host' => 'attacker.example',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Port' => '5252',
            ])
            ->get('http://sdm.dmrc.org/dashboard')
            ->assertRedirect('http://sdm.dmrc.org/login');
    }

    public function test_forwarded_headers_are_ignored_when_no_proxy_is_configured(): void
    {
        config(['trustedproxy.proxies' => []]);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'X-Forwarded-Host' => 'attacker.example',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Port' => '5252',
            ])
            ->get('http://sdm.dmrc.org/dashboard')
            ->assertRedirect('http://sdm.dmrc.org/login');
    }
}
