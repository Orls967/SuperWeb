<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Application\Services\AntiAbuseRateLimiterService;
use Tests\TestCase;

class AntiAbuseRateLimiterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_venue_age_verification_with_biometric_and_rate_limiting(): void
    {
        $service = app(AntiAbuseRateLimiterService::class);
        $visitor = 'VISITOR-VENUE-991';
        $validHash = hash('sha256', 'biometric-face-sample-12345');

        // Valid age and biometric hash
        $result = $service->verifyVenueAge($visitor, 22, $validHash, 21);
        $this->assertTrue($result['allowed']);
        $this->assertSame('AGE_VERIFIED', $result['reason']);

        // Underage
        $underageVisitor = 'VISITOR-UNDERAGE-101';
        $resultUnderage = $service->verifyVenueAge($underageVisitor, 19, $validHash, 21);
        $this->assertFalse($resultUnderage['allowed']);
        $this->assertSame('UNDERAGE_FORBIDDEN', $resultUnderage['reason']);

        // Invalid biometric hash
        $resultBadHash = $service->verifyVenueAge('VISITOR-BAD-HASH', 25, 'short', 21);
        $this->assertFalse($resultBadHash['allowed']);
        $this->assertSame('INVALID_BIOMETRIC_HASH', $resultBadHash['reason']);

        // Repeated attempts lockout
        $spammer = 'VISITOR-BRUTE-BOT';
        for ($i = 0; $i < 5; $i++) {
            $service->verifyVenueAge($spammer, 18, $validHash, 21);
        }
        $lockoutResult = $service->verifyVenueAge($spammer, 25, $validHash, 21);
        $this->assertFalse($lockoutResult['allowed']);
        $this->assertSame('RATE_LIMIT_EXCEEDED_BIOMETRIC_LOCKOUT', $lockoutResult['reason']);
        $this->assertSame(0, $lockoutResult['attempts_remaining']);
    }

    public function test_igd_emergency_room_access_locks_out_after_repeated_failures(): void
    {
        $service = app(AntiAbuseRateLimiterService::class);
        $staff = 'DOCTOR-007';
        $terminalIp = '10.10.99.45';

        // 1st failure
        $res1 = $service->evaluateIgdAccess($staff, $terminalIp, 'FAILURE');
        $this->assertFalse($res1['is_blocked']);
        $this->assertSame(1, $res1['failure_count']);

        // 2nd failure
        $res2 = $service->evaluateIgdAccess($staff, $terminalIp, 'FAILURE');
        $this->assertFalse($res2['is_blocked']);
        $this->assertSame(2, $res2['failure_count']);

        // 3rd failure triggers lockout
        $res3 = $service->evaluateIgdAccess($staff, $terminalIp, 'FAILURE');
        $this->assertTrue($res3['is_blocked']);
        $this->assertSame('BRUTEFORCE_THRESHOLD_REACHED', $res3['reason']);

        // Subsequent attempt blocked immediately
        $res4 = $service->evaluateIgdAccess($staff, $terminalIp, 'SUCCESS');
        $this->assertTrue($res4['is_blocked']);
        $this->assertSame('TERMINAL_IGD_LOCKED_OUT', $res4['reason']);
    }

    public function test_mass_booking_anti_scalping_limits_quantity(): void
    {
        $service = app(AntiAbuseRateLimiterService::class);
        $user = 'USER-CONCERT-FAN';

        // Booking 2 tickets out of 4 allowed
        $res1 = $service->evaluateAntiScalping($user, 'CONCERT_TICKET', 2, 4);
        $this->assertTrue($res1['allowed']);
        $this->assertSame(2, $res1['allocated_qty']);

        // Booking 2 more tickets (reaches 4 limit)
        $res2 = $service->evaluateAntiScalping($user, 'CONCERT_TICKET', 2, 4);
        $this->assertTrue($res2['allowed']);
        $this->assertSame(2, $res2['allocated_qty']);

        // Booking 1 additional ticket exceeds window cap
        $res3 = $service->evaluateAntiScalping($user, 'CONCERT_TICKET', 1, 4);
        $this->assertFalse($res3['allowed']);
        $this->assertSame('ANTI_SCALPING_CAP_EXCEEDED', $res3['reason']);
        $this->assertSame(0, $res3['allocated_qty']);
    }

    public function test_telematics_device_token_rotation_and_grace_period(): void
    {
        $service = app(AntiAbuseRateLimiterService::class);
        $device = 'IOT-FLEET-TRACKER-X88';

        // Initial token provisioning
        $rot1 = $service->rotateTelematicsToken($device, '');
        $this->assertSame('ROTATED_SUCCESSFULLY', $rot1['status']);
        $token1 = $rot1['new_token'];
        $this->assertNotNull($token1);
        $this->assertTrue($service->validateTelematicsToken($device, $token1));

        // Reject rotation with bad token
        $badRot = $service->rotateTelematicsToken($device, 'invalid-supposed-token');
        $this->assertSame('INVALID_OLD_TOKEN_REJECTED', $badRot['status']);

        // Rotate with valid current token
        $rot2 = $service->rotateTelematicsToken($device, $token1);
        $this->assertSame('ROTATED_SUCCESSFULLY', $rot2['status']);
        $token2 = $rot2['new_token'];
        $this->assertNotNull($token2);

        // Both new token and old token (during grace period) are valid
        $this->assertTrue($service->validateTelematicsToken($device, $token2));
        $this->assertTrue($service->validateTelematicsToken($device, $token1));

        // Random bogus token is invalid
        $this->assertFalse($service->validateTelematicsToken($device, 'completely_unknown_token'));
    }
}
