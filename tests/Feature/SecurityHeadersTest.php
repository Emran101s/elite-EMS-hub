<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_responses_carry_the_baseline_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
    }
}
