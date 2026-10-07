<?php

namespace Modules\Hcm\tests\Feature\Gig;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Hcm\Application\Services\Gig\InternalGigEconomyService;
use Tests\TestCase;

class InternalGigEconomyTest extends TestCase
{
    use RefreshDatabase;

    protected InternalGigEconomyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InternalGigEconomyService::class);

        LedgerAccount::create([
            'code' => 'hcm:gig_cost_expense:IDR',
            'name' => 'Internal Gig Cost Center Expense',
            'asset_code' => 'IDR',
            'kind' => 'expense',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => 'hcm:bounty_payout:IDR',
            'name' => 'HCM Bounty Payout Wallet',
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);
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
            hourlyRateIdr: 80_000
        );

        $claim = $this->service->claimBounty($bounty, employeeId: 202);

        // Approve 4.0 hours worked
        // Overtime rate 1.5x -> 4.0 * 80,000 * 1.5 = 480,000 IDR
        $pof = $this->service->approveProofOfWorkAndPayout(
            claim: $claim,
            actualHoursWorked: 4.0,
            geoLocationHash: 'GEO-CK01-KITCHEN-GPS-9988',
            supervisorUserId: 15
        );

        $this->assertEquals(480_000, $pof->calculated_overtime_pay_idr);
        $this->assertEquals('APPROVED', $pof->status);
        $this->assertEquals('PAID', $claim->refresh()->status);
        $this->assertEquals('COMPLETED', $bounty->refresh()->status);

        // Verify ledger balances
        $exp = LedgerAccount::where('code', 'hcm:gig_cost_expense:IDR')->first();
        $payout = LedgerAccount::where('code', 'hcm:bounty_payout:IDR')->first();
        $this->assertEquals('480000', (string) $exp->cached_balance);
        $this->assertEquals('-480000', (string) $payout->cached_balance);
    }
}
