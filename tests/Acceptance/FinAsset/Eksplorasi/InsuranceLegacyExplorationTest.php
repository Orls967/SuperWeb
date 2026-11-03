<?php

declare(strict_types=1);

namespace Tests\Acceptance\FinAsset\Eksplorasi;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Insurance\Application\Actions\IssueEmbeddedMicroInsurancePolicyAction;
use Modules\Insurance\Application\Actions\SettleApprovedInsuranceClaimLedgerAction;
use Modules\Insurance\Application\Actions\TriggerParametricOracleAutoClaimAction;
use Modules\Insurance\Domain\Models\InsuranceClaim;
use Modules\Insurance\Domain\Models\InsurancePolicy;
use Modules\Insurance\Domain\Models\InsuranceProduct;
use Modules\Insurance\Ledger\InsuranceAccounts;
use Tests\TestCase;

/**
 * L4.0 Bukti Kondisi Kode Lama: Eksplorasi Modul Insurance (§2F Slice 2).
 *
 * Klasifikasi Action:
 * - IssueEmbeddedMicroInsurancePolicyAction: SEBAGIAN (non-deterministic rand() policy number, no gapless lock, no unearned premium ledger posting)
 * - TriggerParametricOracleAutoClaimAction: SEBAGIAN (no oracle registry validation, hardcoded pending_review status without fraud score STP evaluation)
 * - SettleApprovedInsuranceClaimLedgerAction: KERANGKA (named LedgerAction but has zero double-entry ledger postings)
 */
class InsuranceLegacyExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected User $policyHolder;

    protected InsuranceProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->policyHolder = User::factory()->create();
        $this->policyHolder->walletAccount('IDR');

        $this->product = InsuranceProduct::create([
            'product_code' => 'EXP_DELAY',
            'name' => 'Exploration Transit Delay Insurance',
            'trigger_event_type' => 'lgx.late',
            'premium_amount_idr' => 25_000,
            'max_payout_idr' => 250_000,
            'is_embedded' => true,
        ]);
    }

    public function test_legacy_tables_exist_and_missing_tables_identified(): void
    {
        expect(Schema::hasTable('ins_products'))->toBeTrue();
        expect(Schema::hasTable('ins_policies'))->toBeTrue();
        expect(Schema::hasTable('ins_claims'))->toBeTrue();

        // Extended tables required by KONSEP §2F and I-1..I-5
        expect(Schema::hasTable('ins_policy_sequences'))->toBeTrue(); // I-1 Gapless sequence counter
        expect(Schema::hasTable('ins_policy_earnings'))->toBeTrue();  // I-2 Daily cumulative premium earning
        expect(Schema::hasTable('ins_parametric_oracles'))->toBeTrue(); // I-3 Oracle registry
    }

    public function test_legacy_ledger_accounts_missing_unearned_and_earned_premium(): void
    {
        $definitions = InsuranceAccounts::definitions();
        $codes = array_column($definitions, 'code');

        // Accounts include unearned premium liability and earned premium revenue
        expect($codes)->toContain('ins:premium_reserve:IDR');
        expect($codes)->toContain('ins:unearned_premium:IDR');
        expect($codes)->toContain('ins:earned_premium_revenue:IDR');
    }

    public function test_issue_policy_action_status_sebagian(): void
    {
        $action = app(IssueEmbeddedMicroInsurancePolicyAction::class);
        $res = $action->handle(
            productId: $this->product->id,
            userId: $this->policyHolder->id,
            subjectRefType: 'order',
            subjectRefId: 'ORD-999',
            durationDays: 30
        );

        expect($res['policy'])->toBeInstanceOf(InsurancePolicy::class);
        expect($res['premium_paid_idr'])->toBe(25_000);

        // Weakness: Policy number uses non-deterministic rand(100, 999) rather than gapless sequence POL-{PROD}-{YEAR}-{000001}
        expect($res['policy_number'])->toMatch('/^POL-[A-Z_]+-\d{14}-\d{3}$/');
    }

    public function test_trigger_parametric_claim_action_status_sebagian(): void
    {
        $policy = InsurancePolicy::create([
            'policy_number' => 'POL-EXP-001',
            'product_id' => $this->product->id,
            'user_id' => $this->policyHolder->id,
            'premium_paid_idr' => 25_000,
            'coverage_limit_idr' => 250_000,
            'starts_at' => now()->subDays(5),
            'expires_at' => now()->addDays(25),
            'status' => 'active',
        ]);

        $action = app(TriggerParametricOracleAutoClaimAction::class);
        $res = $action->handle(
            policyId: $policy->id,
            triggerEventId: 'EVT-WEATHER-404',
            claimAmountIdr: 250_000,
            evidencePayload: ['rainfall_mm' => 150]
        );

        expect($res['claim'])->toBeInstanceOf(InsuranceClaim::class);
        expect($res['payout_amount_idr'])->toBe(250_000);

        // Weakness: Status is hardcoded to pending_review, missing STP approval when fraud score is low
        expect($res['status'])->toBe('pending_review');
    }

    public function test_settle_claim_action_status_kerangka(): void
    {
        $policy = InsurancePolicy::create([
            'policy_number' => 'POL-EXP-002',
            'product_id' => $this->product->id,
            'user_id' => $this->policyHolder->id,
            'premium_paid_idr' => 25_000,
            'coverage_limit_idr' => 250_000,
            'starts_at' => now()->subDays(5),
            'expires_at' => now()->addDays(25),
            'status' => 'active',
        ]);

        $claim = InsuranceClaim::create([
            'claim_number' => 'CLM-EXP-001',
            'policy_id' => $policy->id,
            'trigger_event_id' => 'EVT-WEATHER-405',
            'payout_amount_idr' => 250_000,
            'status' => 'approved',
            'idempotency_key' => 'clm:exp:001',
        ]);

        $action = app(SettleApprovedInsuranceClaimLedgerAction::class);
        $res = $action->handle($claim->id);

        expect($res['status'])->toBe('paid');
        expect($res['paid_at'])->not->toBeNull();

        // Weakness: Named SettleApprovedInsuranceClaimLedgerAction, but has zero double-entry ledger postings!
        // No debit to Claim Expense and no credit to Cash/Payable
    }
}
