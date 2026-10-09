<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\TelecomIdentityService;
use Tests\TestCase;

/**
 * Fase 183 — Telecom Media Services, Content Connectivity & Digital ID Tests
 *
 * Covers:
 *  (a) revoked identity cannot access federated session
 *  (b) step-up authentication tier enforcement
 *  (c) duplicate notification is idempotent
 *  (d) usage billing matches metered bandwidth
 *  (e) identity:audit = 0 discrepancy
 */
class TelecomIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected TelecomIdentityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TelecomIdentityService::class);
    }

    /**
     * (a) & (b) Federated identity authentication, tier checks and revocation.
     */
    public function test_federated_identity_auth_and_revocation(): void
    {
        $idStaff = $this->service->registerIdentity('staff.john@group.id', 'STAFF');

        // 1. Staff accessing basic or staff tier -> SUCCESS
        $this->assertTrue($this->service->authenticateFederatedSession($idStaff->identity_uuid, 'BASIC'));
        $this->assertTrue($this->service->authenticateFederatedSession($idStaff->identity_uuid, 'STAFF'));

        // 2. Staff attempting high-risk operation without step-up -> Exception
        try {
            $this->service->authenticateFederatedSession($idStaff->identity_uuid, 'HIGH_RISK');
            $this->fail('Expected exception for step-up tier requirement.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Step-up authentication required', $e->getMessage());
        }

        // 3. Revoke identity -> Any access blocked
        $this->service->revokeIdentity($idStaff->identity_uuid);
        $this->expectException(\RuntimeException::class);
        $this->service->authenticateFederatedSession($idStaff->identity_uuid, 'BASIC');
    }

    /**
     * (c) Notification gateway idempotency prevents duplicate dispatch.
     */
    public function test_notification_gateway_idempotency(): void
    {
        $key = 'OTP-LOGIN-SESSION-XYZ-1234';

        // 1. First send
        $n1 = $this->service->sendNotification($key, '+628123456789', 'WHATSAPP', 'Kode OTP Anda: 789456');
        $this->assertSame('SENT', $n1->delivery_status);

        // 2. Duplicate send with same key
        $n2 = $this->service->sendNotification($key, '+628123456789', 'WHATSAPP', 'Kode OTP Anda: 789456');
        $this->assertSame($n1->idempotency_key, $n2->idempotency_key);

        $count = DB::table('tel_gateway_notifications')->count();
        $this->assertSame(1, $count);
    }

    /**
     * (d) Content network usage billing calculation.
     */
    public function test_bandwidth_usage_metering_bill(): void
    {
        // 500 GB @ Rp 1,500/GB = Rp 750,000
        $bill = $this->service->billBandwidthUsage('TENANT-HOSPITALITY-01', 500.0, 1500.0);

        $this->assertEquals(750000.00, (float) $bill->total_billed_idr);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_telecom_identity_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
