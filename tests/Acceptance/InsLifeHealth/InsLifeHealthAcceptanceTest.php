<?php

declare(strict_types=1);

namespace Tests\Acceptance\InsLifeHealth;

use App\Models\User;
use App\Quality\Audit\Fixtures\LifeHealthAuditCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Insurance\Application\Actions\AdjudicateHealthClaimAction;
use Modules\Insurance\Application\Actions\AuthorizeHospitalCashlessAction;
use Modules\Insurance\Application\Actions\ClaimLifeBenefitAction;
use Modules\Insurance\Application\Actions\IssueLifePolicyAction;
use Modules\Insurance\Application\Actions\RecordWellnessActivityAction;
use Modules\Insurance\Domain\Models\HealthPolicy;
use Modules\Insurance\Domain\Models\UnitLinkFund;
use Tests\TestCase;

final class InsLifeHealthAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_1_akses_kontrol_rute_life_health_wellness_unitlink(): void
    {
        $guestUser = User::factory()->create(['role' => 'guest']);
        $underwriter = User::factory()->create(['role' => 'underwriter']);
        $adjuster = User::factory()->create(['role' => 'claims_adjuster']);
        $customer = User::factory()->create(['role' => 'customer']);

        // Tamu ditolak 403 pada rute admin/underwriter
        $this->actingAs($guestUser)->get(route('insurance.life.index'))->assertForbidden();
        $this->actingAs($guestUser)->get(route('insurance.health.index'))->assertForbidden();
        $this->actingAs($guestUser)->get(route('insurance.wellness.dashboard'))->assertForbidden();

        // Underwriter diizinkan akses katalog jiwa & kesehatan
        $this->actingAs($underwriter)->get(route('insurance.life.index'))->assertOk();
        $this->actingAs($underwriter)->get(route('insurance.health.index'))->assertOk();
        $this->actingAs($underwriter)->get(route('insurance.unitlink.portfolio'))->assertOk();

        // Claims adjuster diizinkan akses GL dan klaim
        $this->actingAs($adjuster)->get(route('insurance.health.gl.index'))->assertOk();
        $this->actingAs($adjuster)->get(route('insurance.health.claims.index'))->assertOk();

        // Customer diizinkan akses dashboard wellness
        $this->actingAs($customer)->get(route('insurance.wellness.dashboard'))->assertOk();
    }

    public function test_2_alur_utama_life_policy_issuance_kalkulasi_a7_ledger(): void
    {
        // Setup Unit Link Fund
        $fund = UnitLinkFund::create([
            'fund_code' => 'UL-EQ-01',
            'name' => 'Nusantara Aggressive Equity',
            'asset_class' => 'EQUITY',
            'current_nav_per_unit_idr' => 1500, // Rp 1.500 per unit
            'total_aum_idr' => 1500000000,
            'total_units' => 1000000,
            'management_fee_bps' => 150,
            'is_active' => true,
        ]);

        $action = app(IssueLifePolicyAction::class);

        $policy = $action->execute(
            policyNumber: 'POL-LIFE-2026-001',
            insuredPartyId: '00000000-0000-0000-0000-000000000001',
            planType: 'TERM_LIFE',
            entryAge: 30,
            termYears: 20,
            sumInsuredIdr: 1000000000, // Rp 1 Miliar
            beneficiaries: [
                [
                    'beneficiary_party_id' => '00000000-0000-0000-0000-000000000002',
                    'relationship' => 'SPOUSE',
                    'share_percentage_bps' => 6000, // 60%
                    'is_primary' => true,
                ],
                [
                    'beneficiary_party_id' => '00000000-0000-0000-0000-000000000003',
                    'relationship' => 'CHILD',
                    'share_percentage_bps' => 4000, // 40%
                    'is_primary' => true,
                ],
            ],
            actorId: 'usr-uw-1',
            idempotencyKey: 'life:iss:test-001',
            unitLinkAllocations: [
                ['fund_code' => 'UL-EQ-01', 'allocation_pct' => 100],
            ]
        );

        expect($policy->policy_number)->toBe('POL-LIFE-2026-001');
        expect($policy->annual_premium_idr)->toBeGreaterThan(0);
        expect($policy->beneficiaries)->toHaveCount(2);
        expect($policy->unitHoldings)->toHaveCount(1);
        expect($policy->unitHoldings->first()->units_held)->toBeGreaterThan(0);
        expect($policy->ledger_transaction_id)->not->toBeNull();
    }

    public function test_3_cashless_hospital_admission_guarantee_letter_dan_klaim(): void
    {
        $policy = HealthPolicy::create([
            'policy_number' => 'POL-HLTH-2026-001',
            'member_party_id' => '00000000-0000-0000-0000-000000000001',
            'plan_tier' => 'PLATINUM',
            'annual_limit_idr' => 200000000, // Rp 200jt
            'annual_utilized_idr' => 0,
            'room_daily_limit_idr' => 1500000,
            'deductible_idr' => 500000,
            'status' => 'active',
            'effective_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
        ]);

        $glAction = app(AuthorizeHospitalCashlessAction::class);
        $gl = $glAction->execute(
            policyNumber: 'POL-HLTH-2026-001',
            hospitalEncounterRef: 'ENC-RS-0099',
            actorId: 'usr-adj-1',
            idempotencyKey: 'gl:test-001'
        );

        expect($gl->gl_number)->toStartWith('GL-');
        expect($gl->approved_ceiling_idr)->toBeGreaterThan(0);
        expect($gl->status)->toBe('issued');

        // Adjudikasi final tagihan RS
        $claimAction = app(AdjudicateHealthClaimAction::class);
        $claim = $claimAction->execute(
            claimNumber: 'CLM-HLTH-2026-001',
            policyNumber: 'POL-HLTH-2026-001',
            claimType: 'CASHLESS',
            incurredAmountIdr: 4500000,
            glNumber: $gl->gl_number,
            actorId: 'usr-adj-1',
            idempotencyKey: 'clm:test-001'
        );

        expect($claim->approved_amount_idr)->toBe(4000000); // 4.5jt - deductible 500rb
        expect($claim->patient_share_idr)->toBe(500000);
        expect($claim->status)->toBe('settled');
        expect($policy->fresh()->annual_utilized_idr)->toBe(4000000);
    }

    public function test_4_wellness_activity_tracking_dan_guard_anti_gaming(): void
    {
        $action = app(RecordWellnessActivityAction::class);

        // 1. Aktivitas Sehat Genuine (10.000 langkah, tidur 8 jam, detak 70 bpm)
        $genuine = $action->execute(
            userPartyId: '00000000-0000-0000-0000-000000000001',
            logDate: now()->toDateString(),
            dailySteps: 10500,
            avgHeartRateBpm: 72,
            sleepMinutes: 480,
            deviceHash: 'dev-apple-watch-s9'
        );

        expect($genuine['anti_gaming_flagged'])->toBeFalse();
        expect($genuine['log']->health_score)->toBeGreaterThanOrEqual(80);
        expect($genuine['reward'])->not->toBeNull();
        expect($genuine['reward']->reward_type)->toBe('PREMIUM_DISCOUNT_BPS');
        expect($genuine['reward']->reward_value)->toBe(500); // 5% diskon

        // 2. Aktivitas Palsu / Manipulasi Bot (> 50.000 langkah)
        $botAttempt = $action->execute(
            userPartyId: '00000000-0000-0000-0000-000000000002',
            logDate: now()->toDateString(),
            dailySteps: 85000, // Anomali pedometer
            avgHeartRateBpm: 75,
            sleepMinutes: 300,
            deviceHash: 'dev-emulator-android'
        );

        expect($botAttempt['anti_gaming_flagged'])->toBeTrue();
        expect($botAttempt['reward'])->toBeNull(); // Reward diblokir
        expect($botAttempt['log']->is_flagged_gaming)->toBeTrue();
    }

    public function test_5_four_eyes_death_claim_dan_audit_dua_sumber(): void
    {
        // 1. Terbitkan Polis Jiwa
        $issueAction = app(IssueLifePolicyAction::class);
        $policy = $issueAction->execute(
            policyNumber: 'POL-LIFE-FE-001',
            insuredPartyId: '00000000-0000-0000-0000-000000000001',
            planType: 'TERM_LIFE',
            entryAge: 40,
            termYears: 10,
            sumInsuredIdr: 500000000,
            beneficiaries: [
                [
                    'beneficiary_party_id' => '00000000-0000-0000-0000-000000000005',
                    'relationship' => 'SPOUSE',
                    'share_percentage_bps' => 10000, // 100%
                ],
            ],
            actorId: 'usr-1',
            idempotencyKey: 'fe:test-001'
        );

        $claimAction = app(ClaimLifeBenefitAction::class);

        // Sabotase four-eyes: adjuster dan approver orang yang sama harus ditolak
        expect(fn () => $claimAction->execute(
            policyNumber: 'POL-LIFE-FE-001',
            deathCertificateRef: 'AKTA-KEMATIAN-999',
            adjusterId: 'usr-same-person',
            approverId: 'usr-same-person',
            idempotencyKey: 'fe:claim-sabotage'
        ))->toThrow(\InvalidArgumentException::class, 'Klaim asuransi jiwa membutuhkan persetujuan empat-mata');

        // Four-eyes valid (dua pihak berbeda)
        $claimResult = $claimAction->execute(
            policyNumber: 'POL-LIFE-FE-001',
            deathCertificateRef: 'AKTA-KEMATIAN-999',
            adjusterId: 'adjuster-siti',
            approverId: 'director-budi',
            idempotencyKey: 'fe:claim-valid'
        );

        expect($claimResult['total_payout_idr'])->toBe(500000000);
        expect($claimResult['beneficiary_payouts'])->toHaveCount(1);
        expect($claimResult['beneficiary_payouts'][0]['amount_idr'])->toBe(500000000);
        expect($policy->fresh()->status)->toBe('claimed');

        // Audit dua-sumber bersih (exit 0)
        $exitClean = Artisan::call('ins:life-health-audit');
        expect($exitClean)->toBe(0);

        // Sabotase korupsi audit
        $fixture = new LifeHealthAuditCorruptionFixture;
        $fixture->corrupt();

        $exitCorrupt = Artisan::call('ins:life-health-audit');
        expect($exitCorrupt)->toBe(1);
    }
}
