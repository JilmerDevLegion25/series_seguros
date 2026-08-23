<?php

namespace Tests\Feature;

use Tests\TestCase;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    public function test_security_headers_are_applied(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'same-origin');
        $response->assertHeader('Permissions-Policy');

        $this->assertStringNotContainsString('unsafe-eval', (string) $response->headers->get('Content-Security-Policy'));
    }
}
