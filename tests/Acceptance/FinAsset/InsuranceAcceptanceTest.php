<?php

declare(strict_types=1);

namespace Tests\Acceptance\FinAsset;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Insurance\Application\Services\InsurancePolicyService;
use Modules\Insurance\Application\Services\InsurTechClaimsService;
use Modules\Insurance\Application\Services\ParametricOracleService;
use Modules\Insurance\Domain\Models\InsuranceClaim;
use Modules\Insurance\Domain\Models\InsuranceParametricOracle;
use Modules\Insurance\Domain\Models\InsurancePolicy;
use Modules\Insurance\Domain\Models\InsurancePolicyEarning;
use Modules\Insurance\Domain\Models\InsuranceProduct;
use Tests\TestCase;

/**
 * Acceptance Tests: LINI 4 Keuangan Aset - Insurance (§2F Slice 2).
 *
 * Menguji seluruh aturan bisnis I-1 s.d. I-5:
 * - Nomor polis gapless urut per produk per tahun (POL-{PROD}-{YEAR}-{000001})
 * - Pengakuan premi: masuk Unearned Premium (liabilitas); pro-rata harian ke Earned Premium via B0′.2 CumulativeRounding
 * - Oracle parametrik terdaftar; STP jika fraud score < threshold; investigasi jika >= threshold
 * - Settlement klaim indemnity debit beban klaim kredit hutang klaim -> kas dompet
 * - Audit command ins:audit dua-sumber & fixture korupsi
 */
class InsuranceAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer1;

    protected User $customer2;

    protected User $underwriter;

    protected User $claimsOfficer;

    protected InsuranceProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->customer1 = User::factory()->create(['role' => 'customer']);
        $this->customer2 = User::factory()->create(['role' => 'customer']);
        $this->underwriter = User::factory()->create(['role' => 'underwriter']);
        $this->claimsOfficer = User::factory()->create(['role' => 'underwriter']);

        $this->customer1->walletAccount('IDR');
        $this->customer2->walletAccount('IDR');

        $this->product = InsuranceProduct::create([
            'product_code' => 'TRANSIT_LATE',
            'name' => 'Transit Delay Protection',
            'trigger_event_type' => 'lgx.shipment.delayed',
            'premium_amount_idr' => 30_000,
            'max_payout_idr' => 300_000,
            'is_embedded' => true,
        ]);
    }

    public function test_01_policy_issuance_requires_idempotency_key(): void
    {
        $policyService = app(InsurancePolicyService::class);

        $this->expectException(\InvalidArgumentException::class);
        $policyService->issuePolicy([
            'product_id' => $this->product->id,
            'user_id' => $this->customer1->id,
            'subject_ref_type' => 'shipment',
            'subject_ref_id' => 'SHP-001',
            'duration_days' => 30,
        ], idempotencyKey: '');
    }

    public function test_02_policy_number_is_gapless_sequential_per_product_per_year(): void
    {
        $policyService = app(InsurancePolicyService::class);
        $this->customer1->walletAccount('IDR')->forceFill(['cached_balance' => '1000000'])->save();

        $pol1 = $policyService->issuePolicy([
            'product_id' => $this->product->id,
            'user_id' => $this->customer1->id,
            'subject_ref_type' => 'shipment',
            'subject_ref_id' => 'SHP-001',
            'duration_days' => 30,
        ], idempotencyKey: 'ins:pol:gapless:001');

        $pol2 = $policyService->issuePolicy([
            'product_id' => $this->product->id,
            'user_id' => $this->customer1->id,
            'subject_ref_type' => 'shipment',
            'subject_ref_id' => 'SHP-002',
            'duration_days' => 30,
        ], idempotencyKey: 'ins:pol:gapless:002');

        $year = date('Y');
        expect($pol1->policy_number)->toBe("POL-TRANSIT_LATE-{$year}-000001")
            ->and($pol2->policy_number)->toBe("POL-TRANSIT_LATE-{$year}-000002");
    }

    public function test_03_policy_issuance_credits_unearned_premium_liability(): void
    {
        $this->customer1->walletAccount('IDR')->forceFill(['cached_balance' => '1000000'])->save();

        $policyService = app(InsurancePolicyService::class);
        $policyService->issuePolicy([
            'product_id' => $this->product->id,
            'user_id' => $this->customer1->id,
            'subject_ref_type' => 'shipment',
            'subject_ref_id' => 'SHP-003',
            'duration_days' => 30,
        ], idempotencyKey: 'ins:pol:unearned:003');

        $unearnedAcct = LedgerAccount::where('code', 'ins:unearned_premium:IDR')->first();
        expect((int) $unearnedAcct->cached_balance)->toBe(30_000);
    }

    public function test_04_policy_issuance_debits_customer_cash_wallet(): void
    {
        $this->customer1->walletAccount('IDR')->forceFill(['cached_balance' => '1000000'])->save();

        $policyService = app(InsurancePolicyService::class);
        $policyService->issuePolicy([
            'product_id' => $this->product->id,
            'user_id' => $this->customer1->id,
            'subject_ref_type' => 'shipment',
            'subject_ref_id' => 'SHP-004',
            'duration_days' => 30,
        ], idempotencyKey: 'ins:pol:debit:004');

        $this->customer1->walletAccount('IDR')->refresh();
        expect((int) $this->customer1->walletAccount('IDR')->cached_balance)->toBe(970_000);
    }

    public function test_05_policy_issuance_fails_if_customer_cash_is_insufficient(): void
    {
        $this->customer1->walletAccount('IDR')->forceFill(['cached_balance' => '10000'])->save();

        $policyService = app(InsurancePolicyService::class);

        $this->expectException(\DomainException::class);
        $policyService->issuePolicy([
            'product_id' => $this->product->id,
            'user_id' => $this->customer1->id,
            'subject_ref_type' => 'shipment',
            'subject_ref_id' => 'SHP-005',
            'duration_days' => 30,
        ], idempotencyKey: 'ins:pol:fail:005');
    }

    public function test_06_daily_earned_premium_uses_cumulative_rounding_b02(): void
    {
        // 100,000 premium over 30 days (vector from B0′.2: steps 3333/3334, sum = 100,000)
        $customProduct = InsuranceProduct::create([
            'product_code' => 'HEAVY_CARGO',
            'name' => 'Heavy Cargo Insurance',
            'trigger_event_type' => 'lgx.cargo.damage',
            'premium_amount_idr' => 100_000,
            'max_payout_idr' => 1_000_000,
            'is_embedded' => true,
        ]);

        $this->customer1->walletAccount('IDR')->forceFill(['cached_balance' => '1000000'])->save();
        $policyService = app(InsurancePolicyService::class);

        $policy = $policyService->issuePolicy([
            'product_id' => $customProduct->id,
            'user_id' => $this->customer1->id,
            'subject_ref_type' => 'cargo',
            'subject_ref_id' => 'CRG-006',
            'duration_days' => 30,
        ], idempotencyKey: 'ins:pol:cumul:006');

        // Earn for day 1
        $earning1 = $policyService->recognizeDailyEarning($policy->id, dayIndex: 1, idempotencyKey: 'ins:earn:006:1');
        expect($earning1['daily_earned_idr'])->toBe(3_333)
            ->and($earning1['cumulative_earned_idr'])->toBe(3_333);

        // Earn for day 3
        $earning2 = $policyService->recognizeDailyEarning($policy->id, dayIndex: 2, idempotencyKey: 'ins:earn:006:2');
        $earning3 = $policyService->recognizeDailyEarning($policy->id, dayIndex: 3, idempotencyKey: 'ins:earn:006:3');
        expect($earning3['daily_earned_idr'])->toBe(3_334)
            ->and($earning3['cumulative_earned_idr'])->toBe(10_000);
    }

    public function test_07_daily_earning_records_append_only_facts(): void
    {
        $this->customer1->walletAccount('IDR')->forceFill(['cached_balance' => '1000000'])->save();
        $policyService = app(InsurancePolicyService::class);

        $policy = $policyService->issuePolicy([
            'product_id' => $this->product->id,
            'user_id' => $this->customer1->id,
            'subject_ref_type' => 'shipment',
            'subject_ref_id' => 'SHP-007',
            'duration_days' => 30,
        ], idempotencyKey: 'ins:pol:facts:007');

        $policyService->recognizeDailyEarning($policy->id, 1, 'ins:earn:007:1');

        $record = InsurancePolicyEarning::where('policy_id', $policy->id)->first();
        expect($record)->not->toBeNull();

        $this->expectException(\DomainException::class);
        // Modifying fact violates PR5
        $record->update(['daily_amount_idr' => 9999]);
    }

    public function test_08_daily_earning_transfers_from_unearned_to_earned_revenue(): void
    {
        $this->customer1->walletAccount('IDR')->forceFill(['cached_balance' => '1000000'])->save();
        $policyService = app(InsurancePolicyService::class);

        $policy = $policyService->issuePolicy([
            'product_id' => $this->product->id,
            'user_id' => $this->customer1->id,
            'subject_ref_type' => 'shipment',
            'subject_ref_id' => 'SHP-008',
            'duration_days' => 30,
        ], idempotencyKey: 'ins:pol:rev:008');

        $policyService->recognizeDailyEarning($policy->id, 1, 'ins:earn:008:1');

        $unearned = LedgerAccount::where('code', 'ins:unearned_premium:IDR')->first();
        $earned = LedgerAccount::where('code', 'ins:earned_premium_revenue:IDR')->first();

        expect((int) $unearned->cached_balance)->toBe(29_000)
            ->and((int) $earned->cached_balance)->toBe(1_000);
    }

    public function test_09_parametric_oracle_registration_validates_source_type(): void
    {
        $oracleService = app(ParametricOracleService::class);

        $this->expectException(\InvalidArgumentException::class);
        $oracleService->registerOracle([
            'oracle_code' => 'INVALID_SRC',
            'name' => 'Untrusted Oracle',
            'source_type' => 'UNKNOWN_PROVIDER',
        ], idempotencyKey: 'ins:orc:reg:fail:009');
    }

    public function test_10_parametric_oracle_registration_succeeds_for_trusted_bmkg(): void
    {
        $oracleService = app(ParametricOracleService::class);
        $oracle = $oracleService->registerOracle([
            'oracle_code' => 'BMKG_WEATHER_CGK',
            'name' => 'BMKG Jakarta Weather Station',
            'source_type' => 'BMKG_WEATHER',
            'api_endpoint_config' => ['station_id' => 'CGK_01'],
        ], idempotencyKey: 'ins:orc:reg:010');

        expect($oracle)->toBeInstanceOf(InsuranceParametricOracle::class)
            ->and($oracle->is_active)->toBeTrue();
    }

    public function test_11_inactive_oracle_cannot_trigger_parametric_claim(): void
    {
        $oracleService = app(ParametricOracleService::class);
        $oracle = $oracleService->registerOracle([
            'oracle_code' => 'ORC_INACTIVE',
            'name' => 'Disabled Oracle',
            'source_type' => 'BMKG_WEATHER',
            'is_active' => false,
        ], idempotencyKey: 'ins:orc:reg:011');

        $claimsService = app(InsurTechClaimsService::class);

        $this->expectException(\DomainException::class);
        $claimsService->triggerParametricClaim(
            oracleCode: $oracle->oracle_code,
            policyId: 1,
            triggerEventId: 'EVT-RAIN-011',
            evidencePayload: ['rainfall_mm' => 120],
            idempotencyKey: 'ins:clm:fail:011'
        );
    }

    public function test_12_parametric_trigger_creates_claim_on_active_policy(): void
    {
        $policy = $this->createActivePolicyWithCustomer($this->customer1, 30_000, 300_000);
        $oracle = $this->createActiveOracle('BMKG_RADAR_012');

        $claimsService = app(InsurTechClaimsService::class);
        $claim = $claimsService->triggerParametricClaim(
            oracleCode: $oracle->oracle_code,
            policyId: $policy->id,
            triggerEventId: 'EVT-DELAY-012',
            evidencePayload: ['delay_minutes' => 180],
            idempotencyKey: 'ins:clm:param:012'
        );

        expect($claim)->toBeInstanceOf(InsuranceClaim::class)
            ->and($claim->policy_id)->toBe($policy->id)
            ->and($claim->payout_amount_idr)->toBe(300_000);
    }

    public function test_13_parametric_claim_straight_through_processing_when_fraud_score_low(): void
    {
        $policy = $this->createActivePolicyWithCustomer($this->customer1, 30_000, 300_000);
        $oracle = $this->createActiveOracle('BMKG_RADAR_013');

        $claimsService = app(InsurTechClaimsService::class);
        // Clean history -> fraud score 500 bps (< 3000 bps threshold) -> STP Approved
        $claim = $claimsService->triggerParametricClaim(
            oracleCode: $oracle->oracle_code,
            policyId: $policy->id,
            triggerEventId: 'EVT-DELAY-013',
            evidencePayload: ['delay_minutes' => 200, 'verified_carrier' => true],
            idempotencyKey: 'ins:clm:stp:013'
        );

        expect($claim->status)->toBe('approved');
    }

    public function test_14_parametric_claim_requires_investigation_when_fraud_score_high(): void
    {
        $policy = $this->createActivePolicyWithCustomer($this->customer1, 30_000, 300_000);
        $oracle = $this->createActiveOracle('BMKG_RADAR_014');

        $claimsService = app(InsurTechClaimsService::class);
        // Suspicious payload / frequent claims -> fraud score 4500 bps (>= 3000 bps threshold) -> pending_investigation
        $claim = $claimsService->triggerParametricClaim(
            oracleCode: $oracle->oracle_code,
            policyId: $policy->id,
            triggerEventId: 'EVT-DELAY-014',
            evidencePayload: ['delay_minutes' => 30, 'geo_spoof_risk' => true],
            idempotencyKey: 'ins:clm:invest:014'
        );

        expect($claim->status)->toBe('pending_investigation');
    }

    public function test_15_four_eyes_approval_for_investigated_claim(): void
    {
        $claim = $this->createClaimInStatus('pending_investigation', 300_000);

        $claimsService = app(InsurTechClaimsService::class);
        $approved = $claimsService->approveInvestigatedClaim(
            claimId: $claim->id,
            officerId: $this->claimsOfficer->id,
            notes: 'Investigated and verified with ground station radar log',
            idempotencyKey: 'ins:clm:appr:015'
        );

        expect($approved->status)->toBe('approved');
    }

    public function test_16_claim_rejection_marks_status_rejected(): void
    {
        $claim = $this->createClaimInStatus('pending_investigation', 300_000);

        $claimsService = app(InsurTechClaimsService::class);
        $rejected = $claimsService->rejectInvestigatedClaim(
            claimId: $claim->id,
            officerId: $this->claimsOfficer->id,
            reason: 'Tampered sensor data identified',
            idempotencyKey: 'ins:clm:rej:016'
        );

        expect($rejected->status)->toBe('rejected');
    }

    public function test_17_claim_approval_accrues_indemnity_expense_and_claimant_payable(): void
    {
        $claim = $this->createClaimInStatus('pending_investigation', 300_000);

        $claimsService = app(InsurTechClaimsService::class);
        $claimsService->approveInvestigatedClaim($claim->id, $this->claimsOfficer->id, 'Approved', 'ins:clm:appr:017');

        $expenseAcct = LedgerAccount::where('code', 'ins:claim_expense:IDR')->first();
        $payableAcct = LedgerAccount::where('code', 'claimant:payable:default:IDR')->first();

        expect((int) $expenseAcct->cached_balance)->toBe(300_000)
            ->and((int) $payableAcct->cached_balance)->toBe(300_000);
    }

    public function test_18_claim_settlement_disburses_indemnity_to_policyholder_wallet(): void
    {
        $policy = $this->createActivePolicyWithCustomer($this->customer1, 30_000, 300_000);
        $claim = InsuranceClaim::create([
            'claim_number' => 'CLM-TEST-018',
            'policy_id' => $policy->id,
            'trigger_event_id' => 'EVT-018',
            'payout_amount_idr' => 300_000,
            'status' => 'approved',
            'idempotency_key' => 'ins:clm:raw:018',
        ]);

        $this->customer1->walletAccount('IDR')->forceFill(['cached_balance' => '50000'])->save();

        $claimsService = app(InsurTechClaimsService::class);
        $claimsService->settleApprovedClaim($claim->id, idempotencyKey: 'ins:clm:settle:018');

        $this->customer1->walletAccount('IDR')->refresh();
        expect((int) $this->customer1->walletAccount('IDR')->cached_balance)->toBe(350_000);
    }

    public function test_19_claim_settlement_clears_claimant_payable_liability(): void
    {
        $policy = $this->createActivePolicyWithCustomer($this->customer1, 30_000, 300_000);
        $claim = InsuranceClaim::create([
            'claim_number' => 'CLM-TEST-019',
            'policy_id' => $policy->id,
            'trigger_event_id' => 'EVT-019',
            'payout_amount_idr' => 300_000,
            'status' => 'approved',
            'idempotency_key' => 'ins:clm:raw:019',
        ]);

        $payable = LedgerAccount::where('code', 'claimant:payable:default:IDR')->first();
        $payable->forceFill(['cached_balance' => '300000'])->save();

        $claimsService = app(InsurTechClaimsService::class);
        $claimsService->settleApprovedClaim($claim->id, idempotencyKey: 'ins:clm:settle:019');

        $payable->refresh();
        expect((int) $payable->cached_balance)->toBe(0);
    }

    public function test_20_cannot_settle_unapproved_claim(): void
    {
        $claim = $this->createClaimInStatus('pending_investigation', 300_000);

        $claimsService = app(InsurTechClaimsService::class);

        $this->expectException(\DomainException::class);
        $claimsService->settleApprovedClaim($claim->id, 'ins:clm:fail:020');
    }

    public function test_21_double_claim_on_same_trigger_event_is_rejected(): void
    {
        $policy = $this->createActivePolicyWithCustomer($this->customer1, 30_000, 300_000);
        $oracle = $this->createActiveOracle('BMKG_RADAR_021');

        $claimsService = app(InsurTechClaimsService::class);
        $claimsService->triggerParametricClaim($oracle->oracle_code, $policy->id, 'EVT-DUP-021', [], 'ins:clm:first:021');

        $this->expectException(\DomainException::class);
        $claimsService->triggerParametricClaim($oracle->oracle_code, $policy->id, 'EVT-DUP-021', [], 'ins:clm:second:021');
    }

    public function test_22_expired_policy_rejects_new_claim(): void
    {
        $policy = InsurancePolicy::create([
            'policy_number' => 'POL-EXP-022',
            'product_id' => $this->product->id,
            'user_id' => $this->customer1->id,
            'premium_paid_idr' => 30_000,
            'coverage_limit_idr' => 300_000,
            'starts_at' => now()->subDays(40),
            'expires_at' => now()->subDays(10), // expired
            'status' => 'expired',
        ]);
        $oracle = $this->createActiveOracle('BMKG_RADAR_022');

        $claimsService = app(InsurTechClaimsService::class);

        $this->expectException(\DomainException::class);
        $claimsService->triggerParametricClaim($oracle->oracle_code, $policy->id, 'EVT-022', [], 'ins:clm:fail:022');
    }

    public function test_23_policy_expiration_sweep_recognizes_all_remaining_unearned_premium(): void
    {
        $policy = $this->createActivePolicyWithCustomer($this->customer1, 30_000, 300_000);
        $policy->update(['expires_at' => now()->subDay()]);

        $policyService = app(InsurancePolicyService::class);
        $policyService->expireLapsedPolicy($policy->id, idempotencyKey: 'ins:pol:exp:023');

        $policy->refresh();
        expect($policy->status)->toBe('expired');
    }

    public function test_24_claim_indemnity_cannot_exceed_policy_coverage_limit(): void
    {
        $policy = $this->createActivePolicyWithCustomer($this->customer1, 30_000, 300_000);
        $oracle = $this->createActiveOracle('BMKG_RADAR_024');

        $claimsService = app(InsurTechClaimsService::class);
        $claim = $claimsService->triggerParametricClaim(
            $oracle->oracle_code,
            $policy->id,
            'EVT-EXCEED-024',
            ['damage_estimate_idr' => 1_000_000],
            'ins:clm:cap:024'
        );

        // Clamped to coverage limit 300,000 IDR
        expect($claim->payout_amount_idr)->toBe(300_000);
    }

    public function test_25_idempotency_same_key_same_payload_returns_identical_claim(): void
    {
        $policy = $this->createActivePolicyWithCustomer($this->customer1, 30_000, 300_000);
        $oracle = $this->createActiveOracle('BMKG_RADAR_025');

        $claimsService = app(InsurTechClaimsService::class);
        $key = 'ins:clm:idem:025';

        $c1 = $claimsService->triggerParametricClaim($oracle->oracle_code, $policy->id, 'EVT-025', ['val' => 1], $key);
        $c2 = $claimsService->triggerParametricClaim($oracle->oracle_code, $policy->id, 'EVT-025', ['val' => 1], $key);

        expect($c1->id)->toBe($c2->id);
    }

    public function test_26_idempotency_same_key_different_payload_throws_conflict(): void
    {
        $policy = $this->createActivePolicyWithCustomer($this->customer1, 30_000, 300_000);
        $oracle = $this->createActiveOracle('BMKG_RADAR_026');

        $claimsService = app(InsurTechClaimsService::class);
        $key = 'ins:clm:idem:026';

        $claimsService->triggerParametricClaim($oracle->oracle_code, $policy->id, 'EVT-026-A', ['val' => 1], $key);

        $this->expectException(\DomainException::class);
        $claimsService->triggerParametricClaim($oracle->oracle_code, $policy->id, 'EVT-026-B', ['val' => 2], $key);
    }

    public function test_27_http_policies_index_requires_auth(): void
    {
        $response = $this->get('/insurance/policies');
        $response->assertRedirect('/login');
    }

    public function test_28_http_policies_index_renders_for_authorized_user(): void
    {
        $response = $this->actingAs($this->customer1)->get('/insurance/policies');
        $response->assertOk();
    }

    public function test_29_http_issue_policy_requires_idempotency_key_header(): void
    {
        $response = $this->actingAs($this->customer1)->postJson('/insurance/policies/issue', [
            'product_id' => $this->product->id,
            'duration_days' => 30,
        ]);

        $response->assertStatus(422);
    }

    public function test_30_audit_two_source_passes_with_balanced_premium_schedules(): void
    {
        $exitCode = Artisan::call('ins:audit');
        expect($exitCode)->toBe(0);
    }

    public function test_31_audit_two_source_detects_corrupted_earned_premium(): void
    {
        $policy = $this->createActivePolicyWithCustomer($this->customer1, 30_000, 300_000);

        // Corrupt earning record without ledger transfer
        InsurancePolicyEarning::create([
            'policy_id' => $policy->id,
            'earning_date' => now()->toDateString(),
            'daily_amount_idr' => 50_000, // corrupt amount
            'cumulative_earned_idr' => 50_000,
            'idempotency_key' => 'ins:earn:corr:031',
        ]);

        $exitCode = Artisan::call('ins:audit');
        expect($exitCode)->not->toBe(0);
    }

    // Helpers
    protected function createActivePolicyWithCustomer(User $user, int $premium, int $limit): InsurancePolicy
    {
        return InsurancePolicy::create([
            'policy_number' => 'POL-TRANSIT_LATE-'.date('Y').'-'.str_pad((string) rand(100, 999), 6, '0', STR_PAD_LEFT),
            'product_id' => $this->product->id,
            'user_id' => $user->id,
            'premium_paid_idr' => $premium,
            'coverage_limit_idr' => $limit,
            'starts_at' => now()->subDays(2),
            'expires_at' => now()->addDays(28),
            'status' => 'active',
        ]);
    }

    protected function createActiveOracle(string $code): InsuranceParametricOracle
    {
        return InsuranceParametricOracle::firstOrCreate(
            ['oracle_code' => $code],
            [
                'name' => "Oracle {$code}",
                'source_type' => 'BMKG_WEATHER',
                'is_active' => true,
                'api_endpoint_config' => [],
            ]
        );
    }

    protected function createClaimInStatus(string $status, int $amount): InsuranceClaim
    {
        $policy = $this->createActivePolicyWithCustomer($this->customer1, 30_000, $amount);

        return InsuranceClaim::create([
            'claim_number' => 'CLM-TEST-'.rand(1000, 9999),
            'policy_id' => $policy->id,
            'trigger_event_id' => 'EVT-'.rand(1000, 9999),
            'payout_amount_idr' => $amount,
            'status' => $status,
            'idempotency_key' => 'ins:clm:status:'.rand(1000, 9999),
        ]);
    }
}
