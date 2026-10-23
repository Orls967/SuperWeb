<?php

declare(strict_types=1);

namespace Modules\Hcm\tests\Feature\Gig;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Domain\Models\Role;
use Modules\Hcm\Application\Services\Gig\InternalGigEconomyService;
use Modules\Hcm\Domain\Models\Gig\GovBounty;
use Modules\Hcm\Domain\Models\Gig\GovBountyClaim;
use Modules\Hcm\Domain\Models\Gig\GovBountyPof;
use Tests\TestCase;

class InternalGigEconomyTest extends TestCase
{
    use RefreshDatabase;

    protected InternalGigEconomyService $service;

    protected User $admin;

    protected User $hcmManager;

    protected User $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InternalGigEconomyService::class);

        $this->seed(LedgerAccountsSeeder::class);

        Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrator', 'is_system' => true]);
        Role::firstOrCreate(['name' => 'hcm_manager'], ['label' => 'HCM Manager', 'is_system' => true]);
        Role::firstOrCreate(['name' => 'driver'], ['label' => 'Driver', 'is_system' => true]);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->hcmManager = User::factory()->create(['role' => 'hcm_manager']);
        $this->driver = User::factory()->create(['role' => 'driver']);
    }

    public function test_85_1_and_85_2_claim_matching_with_schedule_conflict_and_max_hours_guardrail(): void
    {
        $start = Carbon::parse('2026-10-20 18:00:00');
        $end = Carbon::parse('2026-10-20 22:00:00'); // 4 hours

        $bounty = $this->service->postBounty(
            costCenter: 'WMS_CIKARANG_01',
            title: 'Urgent Night Shift Container Unloading',
            description: 'Unload 40ft reefer container with cold chain pallets',
            shiftStart: $start,
            shiftEnd: $end,
            hourlyRateIdr: 75_000,
            requiredCertification: 'K3_LOGISTICS'
        );

        $this->assertEquals('OPEN', $bounty->status);
        $this->assertEquals(4.0, (float) $bounty->duration_hours);
        $this->assertNotNull($bounty->embedded_insurance_policy_ref);

        // 1. Missing required certification fails
        try {
            $this->service->claimBounty($bounty, employeeId: 101, employeeCertifications: ['FORKLIFT_ONLY']);
            $this->fail('Should reject due to missing certification');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('lacks required certification', $e->getMessage());
        }

        // 2. Exceeding daily working hours (already worked 5 hours + 4 hours bounty = 9 hrs > 8 hrs limit) fails
        try {
            $this->service->claimBounty(
                $bounty,
                employeeId: 101,
                employeeCertifications: ['K3_LOGISTICS'],
                alreadyWorkedHoursToday: 5.0
            );
            $this->fail('Should reject due to daily hour limits');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('daily working hours limit', $e->getMessage());
        }

        // 3. Successful claim
        $claim = $this->service->claimBounty(
            $bounty,
            employeeId: 101,
            employeeCertifications: ['K3_LOGISTICS'],
            alreadyWorkedHoursToday: 3.0 // 3 + 4 = 7 <= 8 hrs
        );

        $this->assertEquals('ASSIGNED', $claim->status);
        $this->assertEquals(10, $claim->mobility_points_earned);
        $this->assertEquals('CLAIMED', $bounty->refresh()->status);
    }

    public function test_85_3_proof_of_work_approval_calculates_overtime_and_posts_ledger_payout(): void
    {
        $start = Carbon::parse('2026-10-21 14:00:00');
        $end = Carbon::parse('2026-10-21 18:00:00'); // 4 hours @ 80k/hr

        $bounty = $this->service->postBounty(
            costCenter: 'RESTO_CK01',
            title: 'Prep Cook Catering Rush',
            description: 'Assist central kitchen prep for 500 meal boxes',
            shiftStart: $start,
            shiftEnd: $end,
            hourlyRateIdr: 80_000,
            speedBonusIdr: 20_000
        );

        $claim = $this->service->claimBounty($bounty, employeeId: 202);

        // Approve 4.0 hours worked
        // Overtime rate 1.5x -> 4.0 * 80,000 * 1.5 = 480,000 IDR + 20,000 speed bonus = 500,000 IDR
        $pof = $this->service->approveProofOfWorkAndPayout(
            claim: $claim,
            actualHoursWorked: 4.0,
            geoLocationHash: 'GEO-CK01-KITCHEN-GPS-9988',
            supervisorUserId: 15
        );

        $this->assertEquals(500_000, $pof->calculated_overtime_pay_idr);
        $this->assertEquals('APPROVED', $pof->status);
        $this->assertEquals('PAID', $claim->refresh()->status);
        $this->assertEquals('COMPLETED', $bounty->refresh()->status);

        // Verify ledger balances
        $exp = LedgerAccount::where('code', 'hcm:gig_cost_expense:IDR')->first();
        $payout = LedgerAccount::where('code', 'hcm:bounty_payout:IDR')->first();
        $this->assertEquals('500000', (string) $exp->cached_balance);
        $this->assertEquals('-500000', (string) $payout->cached_balance);
    }

    public function test_85_4_weekly_overtime_limit_and_high_risk_permit_mandatory(): void
    {
        $start = Carbon::parse('2026-10-22 08:00:00');
        $end = Carbon::parse('2026-10-22 12:00:00'); // 4 hours

        $bounty = $this->service->postBounty(
            costCenter: 'MALL_DUTA_EVENT',
            title: 'High Scaffold Lighting Rig Setup',
            description: 'Setup lighting truss at 10m height',
            shiftStart: $start,
            shiftEnd: $end,
            hourlyRateIdr: 100_000,
            requiresHighRiskPermit: true
        );

        // 1. Missing high-risk permit fails
        try {
            $this->service->claimBounty(
                $bounty,
                employeeId: 303,
                alreadyWorkedHoursToday: 0.0,
                alreadyWorkedOvertimeThisWeek: 0.0,
                workPermitCode: null
            );
            $this->fail('High-risk permit is mandatory');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('High-risk work permit is mandatory', $e->getMessage());
        }

        // 2. Weekly overtime limit exceeded (> 14 hrs/week) fails
        try {
            $this->service->claimBounty(
                $bounty,
                employeeId: 303,
                alreadyWorkedHoursToday: 0.0,
                alreadyWorkedOvertimeThisWeek: 12.0, // 12 + 4 = 16 > 14
                workPermitCode: 'PERMIT-K3-HEIGHT-01'
            );
            $this->fail('Weekly overtime limit must reject');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('weekly overtime limit', $e->getMessage());
        }

        // 3. Valid claim with permit succeeds
        $claim = $this->service->claimBounty(
            $bounty,
            employeeId: 303,
            alreadyWorkedHoursToday: 0.0,
            alreadyWorkedOvertimeThisWeek: 8.0, // 8 + 4 = 12 <= 14
            workPermitCode: 'PERMIT-K3-HEIGHT-01'
        );
        $this->assertEquals('PERMIT-K3-HEIGHT-01', $claim->work_permit_code);
    }

    public function test_85_5_supervisor_self_approval_and_duplicate_pof_rejected(): void
    {
        $start = Carbon::parse('2026-10-23 10:00:00');
        $end = Carbon::parse('2026-10-23 14:00:00');

        $bounty = $this->service->postBounty(
            costCenter: 'LOGISTICS_FLEET',
            title: 'Fleet Heavy Wash & Degrease',
            description: 'Wash 5 prime movers after intercity haul',
            shiftStart: $start,
            shiftEnd: $end,
            hourlyRateIdr: 70_000
        );

        $claim = $this->service->claimBounty($bounty, employeeId: 404);

        // 1. Self-approval by same employee rejected
        try {
            $this->service->approveProofOfWorkAndPayout(
                claim: $claim,
                actualHoursWorked: 4.0,
                geoLocationHash: 'GEO-FLEET-POOL-01',
                supervisorUserId: 404 // Same as employee!
            );
            $this->fail('Self-approval must be rejected');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Supervisor cannot approve their own', $e->getMessage());
        }

        // 2. Valid approval by supervisor 88 succeeds
        $pof = $this->service->approveProofOfWorkAndPayout(
            claim: $claim,
            actualHoursWorked: 4.0,
            geoLocationHash: 'GEO-FLEET-POOL-01',
            supervisorUserId: 88
        );
        $this->assertEquals('APPROVED', $pof->status);

        // 3. Second approval on already PAID claim rejected (anti double-payout)
        try {
            $this->service->approveProofOfWorkAndPayout(
                claim: $claim,
                actualHoursWorked: 4.0,
                geoLocationHash: 'GEO-FLEET-POOL-01',
                supervisorUserId: 88
            );
            $this->fail('Double approval must be rejected');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Cannot approve POF for claim in status PAID', $e->getMessage());
        }

        // 4. Test marketplace summary
        $summary = $this->service->getBountyMarketplaceSummary();
        $this->assertGreaterThanOrEqual(1, $summary['total_bounties']);
        $this->assertGreaterThanOrEqual(1, $summary['completed_bounties']);
        $this->assertGreaterThan(0, $summary['total_payout_idr']);
        $this->assertGreaterThanOrEqual(10, $summary['total_mobility_points']);
    }

    public function test_85_hcm_audit_command_and_corruption_detection(): void
    {
        // 1. Clean audit passes
        $this->artisan('hcm:audit')->assertSuccessful();

        // 2. Corrupt gig bounty POF (calculated_overtime_pay_idr = 0)
        $start = Carbon::parse('2026-10-24 10:00:00');
        $end = Carbon::parse('2026-10-24 14:00:00');

        $bounty = $this->service->postBounty(
            costCenter: 'RESTO_CK01',
            title: 'Audit Corruption Test Shift',
            description: 'Audit test',
            shiftStart: $start,
            shiftEnd: $end,
            hourlyRateIdr: 60_000
        );

        $claim = $this->service->claimBounty($bounty, employeeId: 505);

        $badPof = GovBountyPof::create([
            'pof_code' => 'POF-BAD-AUDIT-TEST',
            'bounty_claim_id' => $claim->id,
            'actual_hours_worked' => 4.0,
            'overtime_multiplier' => 1.5,
            'geo_location_hash' => 'GEO-BAD',
            'supervisor_user_id' => 99,
            'calculated_overtime_pay_idr' => 0, // violation!
            'status' => 'APPROVED',
        ]);

        $this->artisan('hcm:audit')
            ->assertFailed()
            ->expectsOutputToContain('HCM audit FAILED');

        // Clean up
        $badPof->delete();
        $this->artisan('hcm:audit')->assertSuccessful();
    }

    public function test_85_http_endpoints_and_rbac(): void
    {
        // 1. Unauthorized driver receives 403
        $this->actingAs($this->driver)
            ->get(route('hcm.bounties.index'))
            ->assertForbidden();

        // 2. Authorized hcm_manager accesses marketplace
        $res = $this->actingAs($this->hcmManager)->get(route('hcm.bounties.index'));
        $res->assertOk();
        $res->assertViewIs('hcm::hcm.bounties');

        // 3. Post Bounty POST
        $postBounty = $this->actingAs($this->hcmManager)->post(route('hcm.bounties.store'), [
            'business_unit_cost_center' => 'WMS_CIKARANG_01',
            'title' => 'HTTP Post Shift Test',
            'description' => 'Test shift posted via HTTP',
            'shift_start' => '2026-10-25 08:00:00',
            'shift_end' => '2026-10-25 12:00:00',
            'hourly_rate_idr' => 85_000,
            'speed_bonus_idr' => 25_000,
            'required_certification' => 'K3_LOGISTICS',
        ]);
        $postBounty->assertRedirect();
        $bounty = GovBounty::where('title', 'HTTP Post Shift Test')->first();
        $this->assertNotNull($bounty);

        // 4. Claim Bounty POST
        $postClaim = $this->actingAs($this->hcmManager)->post(route('hcm.bounties.claim', $bounty), [
            'employee_id' => 606,
            'certifications' => 'K3_LOGISTICS',
            'already_worked_hours_today' => 2.0,
            'already_worked_overtime_this_week' => 4.0,
        ]);
        $postClaim->assertRedirect();
        $claim = GovBountyClaim::where('bounty_id', $bounty->id)->first();
        $this->assertNotNull($claim);
        $this->assertEquals('ASSIGNED', $claim->status);

        // 5. Payout POF POST
        $postPayout = $this->actingAs($this->admin)->post(route('hcm.bounties.payout', $claim), [
            'actual_hours_worked' => 4.0,
            'geo_location_hash' => 'GEO-HTTP-VERIFIED-HASH',
        ]);
        $postPayout->assertRedirect();
        $this->assertEquals('PAID', $claim->fresh()->status);
        $this->assertEquals('COMPLETED', $bounty->fresh()->status);
        $pof = GovBountyPof::where('bounty_claim_id', $claim->id)->first();
        $this->assertNotNull($pof);
        $this->assertGreaterThan(0, $pof->calculated_overtime_pay_idr);
    }
}
