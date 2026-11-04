<?php

declare(strict_types=1);

namespace Tests\Acceptance\Gig;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\HcmGigBountyT1Seeder;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Domain\Models\Role;
use Modules\Edu\Contracts\CredentialVerifier;
use Modules\Hcm\Application\Services\Gig\GigBountyMatchingService;
use Modules\Hcm\Application\Services\Gig\InternalGigEconomyService;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Hcm\Domain\Models\Gig\GovBounty;
use Modules\Hcm\Domain\Models\Gig\GovBountyClaim;
use Modules\Hcm\Domain\Models\Gig\GovBountyPof;
use Tests\TestCase;

class GigInternalAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected InternalGigEconomyService $gigService;

    protected GigBountyMatchingService $matcherService;

    protected User $admin;

    protected User $hcmManager;

    protected User $unauthorizedDriver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LedgerAccountsSeeder::class);

        Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrator', 'is_system' => true]);
        Role::firstOrCreate(['name' => 'hcm_manager'], ['label' => 'HCM Manager', 'is_system' => true]);
        Role::firstOrCreate(['name' => 'driver'], ['label' => 'Driver', 'is_system' => true]);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->hcmManager = User::factory()->create(['role' => 'hcm_manager']);
        $this->unauthorizedDriver = User::factory()->create(['role' => 'driver']);

        $this->gigService = app(InternalGigEconomyService::class);
        $this->matcherService = app(GigBountyMatchingService::class);
    }

    public function test_1_rbac_and_anti_idor_route_protection(): void
    {
        // 1. Driver denied from viewing marketplace
        $this->actingAs($this->unauthorizedDriver)
            ->get(route('hcm.bounties.index'))
            ->assertForbidden();

        // 2. HCM Manager allowed
        $this->actingAs($this->hcmManager)
            ->get(route('hcm.bounties.index'))
            ->assertOk();
    }

    public function test_2_edu_credential_verifier_integration_for_certification_guardrail(): void
    {
        // Mock credential verifier
        $mockVerifier = new class implements CredentialVerifier
        {
            public function hasValid(int|string $userId, string $credentialCode, ?CarbonImmutable $at = null): bool
            {
                return $userId === 777 && $credentialCode === 'K3_LOGISTICS';
            }
        };

        $serviceWithVerifier = new InternalGigEconomyService(
            ledgerService: null,
            credentialVerifier: $mockVerifier
        );

        $bounty = $this->gigService->postBounty(
            costCenter: 'WMS_CIKARANG_01',
            title: 'K3 Certified Container Stacking',
            description: 'Stacking pallets safely',
            shiftStart: Carbon::parse('2026-11-10 08:00:00'),
            shiftEnd: Carbon::parse('2026-11-10 12:00:00'),
            hourlyRateIdr: 90_000,
            requiredCertification: 'K3_LOGISTICS'
        );

        // Employee 888 has no credential -> rejected
        try {
            $serviceWithVerifier->claimBounty($bounty, employeeId: 888);
            $this->fail('Should reject uncertified employee');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('lacks required certification', $e->getMessage());
        }

        // Employee 777 has credential via Edu CredentialVerifier -> accepted
        $claim = $serviceWithVerifier->claimBounty($bounty, employeeId: 777);
        $this->assertEquals('ASSIGNED', $claim->status);
        $this->assertEquals(777, $claim->employee_id);
    }

    public function test_3_hungarian_bipartite_matching_with_dual_certificate(): void
    {
        $w1 = new Employee;
        $w1->id = 1;
        $w2 = new Employee;
        $w2->id = 2;

        $b1 = new GovBounty;
        $b1->id = 10;
        $b2 = new GovBounty;
        $b2->id = 20;

        // Skor kecocokan:
        // W1: B1=90, B2=40
        // W2: B1=50, B2=85
        $scoreMatrix = [
            0 => [0 => 90, 1 => 40],
            1 => [0 => 50, 1 => 85],
        ];

        $matchResult = $this->matcherService->matchBounties([$w1, $w2], [$b1, $b2], $scoreMatrix);

        $this->assertTrue($matchResult['is_valid_certificate']);
        $this->assertCount(2, $matchResult['matches']);

        // Optimal: W1 -> B1 (90), W2 -> B2 (85)
        $this->assertEquals(1, $matchResult['matches'][0]['worker_id']);
        $this->assertEquals(10, $matchResult['matches'][0]['bounty_id']);
        $this->assertEquals(2, $matchResult['matches'][1]['worker_id']);
        $this->assertEquals(20, $matchResult['matches'][1]['bounty_id']);
    }

    public function test_4_e2e_pof_approval_ledger_posting_and_idempotency(): void
    {
        $bounty = $this->gigService->postBounty(
            costCenter: 'RESTO_CK01',
            title: 'End of Shift Clean Down',
            description: 'Sanitize commercial kitchen line',
            shiftStart: Carbon::parse('2026-11-10 20:00:00'),
            shiftEnd: Carbon::parse('2026-11-10 23:00:00'),
            hourlyRateIdr: 100_000,
            speedBonusIdr: 25_000
        );

        $claim = $this->gigService->claimBounty($bounty, employeeId: 999);

        // 3 hours * 100k * 1.5 = 450,000 + 25,000 bonus = 475,000 IDR
        $pof = $this->gigService->approveProofOfWorkAndPayout(
            claim: $claim,
            actualHoursWorked: 3.0,
            geoLocationHash: 'GEO-RESTO-CK01-SINK',
            supervisorUserId: 12
        );

        $this->assertEquals(475_000, $pof->calculated_overtime_pay_idr);
        $this->assertEquals('APPROVED', $pof->status);
        $this->assertEquals('PAID', $claim->fresh()->status);
        $this->assertEquals('COMPLETED', $bounty->fresh()->status);

        // Ledger balances
        $exp = LedgerAccount::where('code', 'hcm:gig_cost_expense:IDR')->first();
        $payout = LedgerAccount::where('code', 'hcm:bounty_payout:IDR')->first();
        $this->assertEquals('475000', (string) $exp->cached_balance);
        $this->assertEquals('-475000', (string) $payout->cached_balance);

        // Duplicate approval attempt is rejected
        try {
            $this->gigService->approveProofOfWorkAndPayout(
                claim: $claim,
                actualHoursWorked: 3.0,
                geoLocationHash: 'GEO-RESTO-CK01-SINK',
                supervisorUserId: 12
            );
            $this->fail('Second approval on PAID claim must fail');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Cannot approve POF for claim in status PAID', $e->getMessage());
        }
    }

    public function test_5_seeder_t1_scale_execution(): void
    {
        $this->seed(HcmGigBountyT1Seeder::class);

        $bountyCount = GovBounty::where('bounty_code', 'like', 'BNT-T1-%')->count();
        $claimCount = GovBountyClaim::where('claim_code', 'like', 'CLM-T1-%')->count();

        $this->assertEquals(200, $bountyCount);
        $this->assertEquals(50, $claimCount);
    }

    public function test_6_audit_two_source_reconciliation_and_sabotage_detection(): void
    {
        // 1. Audit passes initially
        $this->artisan('hcm:audit')->assertSuccessful();

        // 2. Introduce deliberate corruption: a POF with 0 payout
        $bounty = $this->gigService->postBounty(
            costCenter: 'GEN',
            title: 'Sabotage Trial Gig',
            description: 'Description',
            shiftStart: Carbon::parse('2026-11-12 10:00:00'),
            shiftEnd: Carbon::parse('2026-11-12 12:00:00'),
            hourlyRateIdr: 50_000
        );
        $claim = $this->gigService->claimBounty($bounty, employeeId: 444);

        $corruptPof = GovBountyPof::create([
            'pof_code' => 'POF-SABOTAGE-999',
            'bounty_claim_id' => $claim->id,
            'actual_hours_worked' => 2.0,
            'overtime_multiplier' => 1.5,
            'geo_location_hash' => 'GEO-SABOTAGE',
            'supervisor_user_id' => 10,
            'calculated_overtime_pay_idr' => 0, // Corrupted!
            'status' => 'APPROVED',
        ]);

        // Audit catches discrepancy
        $this->artisan('hcm:audit')
            ->assertFailed()
            ->expectsOutputToContain('HCM audit FAILED');

        // Cleanup restore
        $corruptPof->delete();
        $this->artisan('hcm:audit')->assertSuccessful();
    }
}
