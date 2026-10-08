<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\SuperAppMiniAppPlatformService;
use Tests\TestCase;

class SuperAppMiniAppPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected SuperAppMiniAppPlatformService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SuperAppMiniAppPlatformService::class);
    }

    public function test_mini_app_registration_and_revenue_share_sum(): void
    {
        // 1. Valid registration with 15% platform + 85% partner (sum = 100%) (315.1 & 315.4)
        $miniApp = $this->service->registerMiniApp(
            miniAppCode: 'APP-EV-PARKING-01',
            partnerId: 'PARTNER_SMART_PARK',
            appName: 'Smart EV Parking & Charging Hub',
            platformRevSharePct: 15.0,
            partnerRevSharePct: 85.0,
            exitClauseAgreed: true
        );
        $this->assertEquals('CERTIFIED', $miniApp->sandbox_status);
        $this->assertTrue((bool) $miniApp->data_portability_exit_clause_agreed);

        // 2. Revenue share sum mismatch (e.g. 20% + 70% = 90% != 100%) throws exception (315.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Revenue share configuration error: Sum of platform');
        $this->service->registerMiniApp('APP-MISMATCH', 'P1', 'App', 20.0, 70.0, true);
    }

    public function test_session_handoff_requires_explicit_user_consent(): void
    {
        // 1. Session handoff without consent throws exception (315.2 Privacy Gate)
        try {
            $this->service->initiateSessionHandoff(
                token: 'TOK-NO-CONSENT-01',
                userId: 'USER_123',
                sourceApp: 'HOST_SUPERAPP',
                targetMiniApp: 'APP-EV-PARKING-01',
                scopedContext: ['location' => 'Jakarta'],
                consentGranted: false
            );
            $this->fail('Expected exception for unconsented session handoff');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Session handoff requires explicit user consent', $e->getMessage());
        }

        // 2. Session handoff with consent succeeds (315.2)
        $handoff = $this->service->initiateSessionHandoff(
            token: 'TOK-VALID-02',
            userId: 'USER_123',
            sourceApp: 'HOST_SUPERAPP',
            targetMiniApp: 'APP-EV-PARKING-01',
            scopedContext: ['location' => 'Jakarta', 'tier' => 'GOLD'],
            consentGranted: true
        );
        $this->assertTrue((bool) $handoff->user_consent_granted);
        $this->assertEquals('TOK-VALID-02', $handoff->handoff_token);
    }

    public function test_failing_mini_app_isolation(): void
    {
        $this->service->registerMiniApp('APP-BUGGY', 'P2', 'Buggy App', 10.0, 90.0, true);

        // Edge case 315.5: Isolate failing mini-app
        $isolated = $this->service->isolateFailingMiniApp('APP-BUGGY');
        $this->assertEquals('CRASHED_ISOLATED', $isolated->sandbox_status);
    }

    public function test_platform_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerMiniApp('APP-AUD', 'PARTNER', 'Audit App', 10.0, 90.0, true);
        $this->service->initiateSessionHandoff('TOK-AUD', 'U1', 'HOST', 'APP-AUD', ['test' => true], true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: revenue share sum mismatch in DB
        DB::table('super_app_mini_app_registry')->insert([
            'mini_app_code' => 'APP-DEFECT-SHARE',
            'partner_id' => 'PARTNER_DEFECT',
            'app_name' => 'Defect',
            'sandbox_status' => 'CERTIFIED',
            'platform_revenue_share_pct' => 10.0,
            'partner_revenue_share_pct' => 40.0, // Sum = 50% != 100%!
            'data_portability_exit_clause_agreed' => true,
            'is_isolated_on_failure' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
