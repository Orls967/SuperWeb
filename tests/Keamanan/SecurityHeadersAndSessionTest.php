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

    /**
     * K-13: Session secure cookie defaults to true in production and false in local development.
     */
    public function test_session_secure_cookie_configuration_k13(): void
    {
        $originalCookie = getenv('SESSION_SECURE_COOKIE');
        $originalEnv = getenv('APP_ENV');

        try {
            putenv('SESSION_SECURE_COOKIE');
            unset($_ENV['SESSION_SECURE_COOKIE'], $_SERVER['SESSION_SECURE_COOKIE']);

            // Production env -> secure true
            putenv('APP_ENV=production');
            $_ENV['APP_ENV'] = 'production';
            $_SERVER['APP_ENV'] = 'production';
            $prodConfig = (require config_path('session.php'))['secure'];
            $this->assertTrue($prodConfig, 'Session cookie must be secure: true in production environment');

            // Local env -> secure false
            putenv('APP_ENV=local');
            $_ENV['APP_ENV'] = 'local';
            $_SERVER['APP_ENV'] = 'local';
            $localConfig = (require config_path('session.php'))['secure'];
            $this->assertFalse($localConfig, 'Session cookie must be secure: false in local environment');
        } finally {
            if ($originalCookie !== false) {
                putenv("SESSION_SECURE_COOKIE={$originalCookie}");
                $_ENV['SESSION_SECURE_COOKIE'] = $originalCookie;
                $_SERVER['SESSION_SECURE_COOKIE'] = $originalCookie;
            } else {
                putenv('SESSION_SECURE_COOKIE');
                unset($_ENV['SESSION_SECURE_COOKIE'], $_SERVER['SESSION_SECURE_COOKIE']);
            }

            if ($originalEnv !== false) {
                putenv("APP_ENV={$originalEnv}");
                $_ENV['APP_ENV'] = $originalEnv;
                $_SERVER['APP_ENV'] = $originalEnv;
            }
        }
    }
}
