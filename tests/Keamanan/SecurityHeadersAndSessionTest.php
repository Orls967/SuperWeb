<?php

declare(strict_types=1);

namespace Tests\Keamanan;

use App\Models\User;
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

        $responseLogin = $this->get('/login');
        $responseLogin->assertHeader('X-Frame-Options', 'DENY');
        $responseLogin->assertHeader('X-Content-Type-Options', 'nosniff');
        $responseLogin->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
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
     * B5: Login sukses tidak dihitung sebagai gagal dan me-reset hit counter.
     */
    public function test_successful_login_does_not_count_as_failure_and_clears_throttle(): void
    {
        $user = User::factory()->create([
            'email' => 'legit-user@example.com',
            'password' => bcrypt('secret123'),
        ]);

        // Gagal 4 kali
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', [
                'email' => 'legit-user@example.com',
                'password' => 'wrongpass',
            ]);
        }

        // Login ke-5 sukses
        $loginSuccess = $this->post('/login', [
            'email' => 'legit-user@example.com',
            'password' => 'secret123',
        ]);
        $loginSuccess->assertRedirect();
        $this->assertAuthenticatedAs($user);

        // Setelah sukses dan logout, throttle harus sudah bersih (tidak 429)
        $this->post('/logout');

        // Login salah 1x lagi tidak boleh langsung 429 karena counter sudah di-clear
        $nextAttempt = $this->post('/login', [
            'email' => 'legit-user@example.com',
            'password' => 'wrongpass',
        ]);
        $this->assertNotEquals(429, $nextAttempt->status());
    }

    /**
     * B5: Kunci throttle memakai email yang dinormalisasi (lowercase + trim).
     */
    public function test_throttle_key_uses_normalized_email(): void
    {
        // Failed login dengan email whitespace dan uppercase
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => '  TARGET-USER@example.COM  ',
                'password' => 'wrongpass',
            ]);
        }

        // Percobaan ke-6 dengan email bersih lowercase harus ter-throttle 429
        $sixthAttempt = $this->post('/login', [
            'email' => 'target-user@example.com',
            'password' => 'wrongpass',
        ]);

        $this->assertEquals(429, $sixthAttempt->status(), 'Kunci throttle harus mengenali email yang dinormalisasi');
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
