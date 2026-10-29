<?php

declare(strict_types=1);

namespace Tests\Keamanan;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('keamanan')]
class SecurityHeadersAndSessionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * S-09: HTTP Responses must include standard defense-in-depth security headers.
     */
    public function test_web_responses_include_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * S-10: Authentication endpoints must have route-level rate limiting.
     */
    public function test_login_attempts_are_throttled_with_http_429(): void
    {
        // Attempt 6 rapid failed logins from the same client
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'attacker@example.com',
                'password' => 'wrongpass',
            ]);
        }

        $sixthAttempt = $this->post('/login', [
            'email' => 'attacker@example.com',
            'password' => 'wrongpass',
        ]);

        $this->assertEquals(429, $sixthAttempt->status(), 'Exceeding login threshold must return HTTP 429 Too Many Requests');
    }
}
