<?php

declare(strict_types=1);

namespace Tests\Acceptance\InsTakaful;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Insurance\Application\Actions\DistributeTakafulSurplusAction;
use Modules\Insurance\Application\Actions\IssueAgriParametricPolicyAction;
use Modules\Insurance\Application\Actions\IssueMicroPolicyAction;
use Modules\Insurance\Application\Actions\IssueTakafulPolicyAction;
use Modules\Insurance\Application\Actions\SettleAgriClaimWithOffsetAction;
use Modules\Insurance\Application\Services\TakafulAuditor;
use Modules\Insurance\Domain\Models\TakafulFund;
use Tests\TestCase;

final class InsTakafulAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_1_akses_kontrol_rute_takaful_agri_micro_dan_command_center(): void
    {
        $guestUser = User::factory()->create(['role' => 'guest']);
        $underwriter = User::factory()->create(['role' => 'underwriter']);
        $customer = User::factory()->create(['role' => 'customer']);

        // Guest ditolak 403
        $this->actingAs($guestUser)->get(route('insurance.takaful.index'))->assertForbidden();
        $this->actingAs($guestUser)->get(route('insurance.agri.index'))->assertForbidden();
        $this->actingAs($guestUser)->get(route('insurance.command.dashboard'))->assertForbidden();

        // Underwriter diizinkan
        $this->actingAs($underwriter)->get(route('insurance.takaful.index'))->assertOk();
        $this->actingAs($underwriter)->get(route('insurance.agri.index'))->assertOk();
        $this->actingAs($underwriter)->get(route('insurance.command.dashboard'))->assertOk();

        // Customer diizinkan rute mikro
        $this->actingAs($customer)->get(route('insurance.micro.index'))->assertOk();
    }

    public function test_2_alur_utama_penerbitan_polis_takaful_pemisahan_tabarru_dan_posting_ledger(): void
    {
        $underwriter = User::factory()->create(['role' => 'underwriter']);

        $fund = TakafulFund::create([
            'fund_code' => 'TK-TEST-FUND',
            'fund_type' => 'TABARRU',
            'name' => 'Dana Tabarru Uji',
            'balance_idr' => 0,
            'total_contributions_idr' => 0,
            'total_claims_paid_idr' => 0,
            'contingency_reserve_idr' => 0,
            'is_active' => true,
        ]);

        $action = app(IssueTakafulPolicyAction::class);

        $participant = $action->execute(
            policyNumber: 'POL-TK-ACC-001',
            participantPartyId: '00000000-0000-0000-0000-000000000001',
            fundCode: 'TK-TEST-FUND',
            planType: 'FAMILY_TAKAFUL',
            annualContributionIdr: 10000000, // Rp 10.000.000
            sumCoveredIdr: 200000000,
            tabarruPortionBps: 7000, // 70%
            ujrahPortionBps: 3000, // 30%
            actorId: (string) $underwriter->id,
            idempotencyKey: 'test:tk:001',
        );

        $this->assertSame('POL-TK-ACC-001', $participant->policy_number);
        $this->assertSame('active', $participant->status);

        $fund->refresh();
        $this->assertSame(7000000, $fund->total_contributions_idr);
        $this->assertSame(7000000, $fund->balance_idr);
    }

    public function test_3_alur_distribusi_surplus_underwriting_dana_tabarru_via_algoritma_a8_bersertifikat(): void
    {
        $underwriter = User::factory()->create(['role' => 'underwriter']);

        $fund = TakafulFund::create([
            'fund_code' => 'TK-SURPLUS-ACC',
            'fund_type' => 'TABARRU',
            'name' => 'Dana Tabarru Surplus',
            'balance_idr' => 5000000000,
            'total_contributions_idr' => 1000000000, // 1 Milyar
            'total_claims_paid_idr' => 400000000, // 400 Juta
            'contingency_reserve_idr' => 0,
            'is_active' => true,
        ]);

        $action = app(DistributeTakafulSurplusAction::class);

        $distribution = $action->execute(
            fundCode: 'TK-SURPLUS-ACC',
            fiscalYear: 2026,
            technicalReserveAdditionIdr: 100000000,
            participantShareBps: 5000,
            contingencyReserveBps: 3000,
            operatorMudharabahBps: 2000,
            actorId: (string) $underwriter->id,
            idempotencyKey: 'test:surplus:2026',
        );

        $this->assertSame(500000000, $distribution->gross_surplus_idr);
        $this->assertSame(250000000, $distribution->participant_share_idr);
        $this->assertSame(150000000, $distribution->contingency_reserve_idr);
        $this->assertSame(100000000, $distribution->mudharabah_operator_idr);
        $this->assertNotEmpty($distribution->calculation_certificate);

        $fund->refresh();
        $this->assertSame(150000000, $fund->contingency_reserve_idr);
    }

    public function test_4_penerbitan_polis_pertanian_parametrik_dan_settlement_klaim_dengan_offset_pinjaman_g7_k2(): void
    {
        $underwriter = User::factory()->create(['role' => 'underwriter']);
        $adjuster = User::factory()->create(['role' => 'claims_adjuster']);

        $issueAction = app(IssueAgriParametricPolicyAction::class);
        $settleAction = app(SettleAgriClaimWithOffsetAction::class);

        $policy = $issueAction->execute(
            policyNumber: 'POL-AGRI-PADI-001',
            farmerPartyId: '00000000-0000-0000-0000-000000000003',
            commodity: 'PADI',
            farmAreaHa: 5,
            sumInsuredIdr: 30000000, // Rp 30.000.000
            premiumAnnualIdr: 900000,
            indexType: 'RAINFALL',
            triggerThresholdVal: 350, // 350 mm curah hujan
            actorId: (string) $underwriter->id,
            idempotencyKey: 'test:agri:001',
        );

        $this->assertSame('POL-AGRI-PADI-001', $policy->policy_number);
        $this->assertSame('active', $policy->status);

        $claim = $settleAction->execute(
            policyNumber: 'POL-AGRI-PADI-001',
            claimNumber: 'CLM-AGRI-PADI-001',
            actualIndexVal: 420,
            actorId: (string) $adjuster->id,
            idempotencyKey: 'test:agri:claim:001',
        );

        $this->assertSame(30000000, $claim->gross_claim_idr);
        $this->assertSame(20000000, $claim->loan_offset_amount_idr);
        $this->assertSame(10000000, $claim->net_farmer_payout_idr);
        $this->assertSame('settled', $claim->status);
    }

    public function test_5_penerbitan_asuransi_mikro_massal_dan_audit_dua_sumber_takaful_agri(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $microAction = app(IssueMicroPolicyAction::class);

        $micro = $microAction->execute(
            policyNumber: 'POL-MICRO-TRANSIT-001',
            memberPartyId: '00000000-0000-0000-0000-000000000004',
            productType: 'TRANSIT_DAILY',
            dailyPremiumIdr: 5000,
            sumInsuredIdr: 20000000,
            durationDays: 3,
            actorId: (string) $customer->id,
            idempotencyKey: 'test:micro:001',
        );

        $this->assertSame('POL-MICRO-TRANSIT-001', $micro->policy_number);
        $this->assertSame('active', $micro->status);

        $auditor = app(TakafulAuditor::class);
        $report = $auditor->audit();

        $this->assertSame('CLEAN', $report['status']);
        $this->assertSame(0, $report['surplus_allocation_mismatches']);
        $this->assertSame(0, $report['contribution_ratio_mismatches']);
        $this->assertSame(0, $report['agri_claim_offset_mismatches']);
    }
}
