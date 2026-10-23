<?php

declare(strict_types=1);

namespace Modules\Insurance\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Insurance\Application\Actions\EvaluateActuarialClaimFraudScoreAction;
use Modules\Insurance\Application\Actions\IssueEmbeddedMicroInsurancePolicyAction;
use Modules\Insurance\Application\Actions\SettleApprovedInsuranceClaimLedgerAction;
use Modules\Insurance\Application\Actions\TriggerParametricOracleAutoClaimAction;
use Modules\Insurance\Domain\Models\InsuranceClaim;
use Modules\Insurance\Domain\Models\InsurancePolicy;
use Modules\Insurance\Domain\Models\InsuranceProduct;
use Tests\TestCase;

class InsuranceEnterpriseDeepeningTest extends TestCase
{
    use RefreshDatabase;

    private function createSampleProduct(): InsuranceProduct
    {
        return InsuranceProduct::create([
            'product_code' => 'INS-TYRE-01',
            'name' => 'Tyre Blowout Micro-Insurance',
            'trigger_event_type' => 'TYRE_PRESSURE_LOSS',
            'premium_amount_idr' => 25000,
            'max_payout_idr' => 1500000,
            'is_embedded' => true,
        ]);
    }

    public function test_issue_embedded_micro_insurance_policy_calculates_premiums_and_coverage(): void
    {
        $user = User::factory()->create();
        $product = $this->createSampleProduct();
        $action = app(IssueEmbeddedMicroInsurancePolicyAction::class);

        $result = $action->handle($product->id, $user->id, 'VEHICLE', 'VEH-999', 45);

        $this->assertEquals(25000, $result['premium_paid_idr']);
        $this->assertEquals(1500000, $result['coverage_limit_idr']);
        $this->assertStringStartsWith('POL-INS-', $result['policy_number']);
        $this->assertEquals('active', $result['policy']->status);
    }

    public function test_trigger_parametric_oracle_auto_claim_creates_claim_within_coverage_limit(): void
    {
        $user = User::factory()->create();
        $product = $this->createSampleProduct();
        $issueAction = app(IssueEmbeddedMicroInsurancePolicyAction::class);
        $policy = $issueAction->handle($product->id, $user->id, 'VEHICLE', 'VEH-888', 30)['policy'];

        $claimAction = app(TriggerParametricOracleAutoClaimAction::class);

        // Claim 2,000,000 IDR, but policy limit is 1,500,000 IDR
        $result = $claimAction->handle($policy->id, 'ORACLE-BLOWOUT-001', 2000000, ['telematics_psi' => 5]);

        $this->assertEquals(1500000, $result['payout_amount_idr']);
        $this->assertEquals('pending_review', $result['status']);
        $this->assertDatabaseHas('ins_claims', [
            'policy_id' => $policy->id,
            'payout_amount_idr' => 1500000,
        ]);
    }

    public function test_cannot_trigger_claim_on_expired_or_inactive_policy(): void
    {
        $user = User::factory()->create();
        $product = $this->createSampleProduct();

        $policy = InsurancePolicy::create([
            'policy_number' => 'POL-EXP-001',
            'product_id' => $product->id,
            'user_id' => $user->id,
            'subject_ref_type' => 'VEHICLE',
            'subject_ref_id' => 'VEH-777',
            'premium_paid_idr' => 25000,
            'coverage_limit_idr' => 1500000,
            'starts_at' => now()->subDays(60),
            'expires_at' => now()->subDays(10), // expired
            'status' => 'active',
        ]);

        $claimAction = app(TriggerParametricOracleAutoClaimAction::class);

        $this->expectException(\DomainException::class);
        $claimAction->handle($policy->id, 'ORACLE-TEST-002', 500000);
    }

    public function test_evaluate_actuarial_claim_fraud_score_auto_approves_low_risk_claim(): void
    {
        $user = User::factory()->create();
        $product = $this->createSampleProduct();

        $policy = InsurancePolicy::create([
            'policy_number' => 'POL-VALID-001',
            'product_id' => $product->id,
            'user_id' => $user->id,
            'subject_ref_type' => 'VEHICLE',
            'subject_ref_id' => 'VEH-666',
            'premium_paid_idr' => 25000,
            'coverage_limit_idr' => 1500000,
            'starts_at' => now()->subDays(20),
            'expires_at' => now()->addDays(10),
            'status' => 'active',
        ]);

        $claim = InsuranceClaim::create([
            'claim_number' => 'CLM-LOW-001',
            'policy_id' => $policy->id,
            'trigger_event_id' => 'EVT-01',
            'payout_amount_idr' => 500000, // < 85% limit
            'evidence_payload' => ['proof' => 'valid'],
            'status' => 'pending_review',
            'idempotency_key' => 'claim-low-001',
        ]);

        $fraudAction = app(EvaluateActuarialClaimFraudScoreAction::class);
        $result = $fraudAction->handle($claim->id);

        $this->assertEquals(0, $result['fraud_score']);
        $this->assertEquals('AUTO_APPROVED', $result['risk_decision']);
        $this->assertEquals('approved', $claim->fresh()->status);
    }

    public function test_settle_approved_insurance_claim_posts_payout_and_marks_paid(): void
    {
        $user = User::factory()->create();
        $product = $this->createSampleProduct();

        $policy = InsurancePolicy::create([
            'policy_number' => 'POL-SETTLE-001',
            'product_id' => $product->id,
            'user_id' => $user->id,
            'subject_ref_type' => 'VEHICLE',
            'subject_ref_id' => 'VEH-555',
            'premium_paid_idr' => 25000,
            'coverage_limit_idr' => 1500000,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
        ]);

        $claim = InsuranceClaim::create([
            'claim_number' => 'CLM-SETTLE-001',
            'policy_id' => $policy->id,
            'trigger_event_id' => 'EVT-SETTLE-01',
            'payout_amount_idr' => 1200000,
            'evidence_payload' => ['verified' => true],
            'status' => 'approved',
            'idempotency_key' => 'claim-settle-001',
        ]);

        $settleAction = app(SettleApprovedInsuranceClaimLedgerAction::class);
        $result = $settleAction->handle($claim->id);

        $this->assertEquals('paid', $result['status']);
        $this->assertEquals(1200000, $result['payout_amount_idr']);
        $this->assertNotNull($claim->fresh()->paid_at);
    }

    public function test_insurance_workflow_http_routes_render_and_handle_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('insurance.workflow.policies'));
        $response->assertStatus(200);
        $response->assertSee('Embedded Micro-Insurance');

        $response = $this->actingAs($admin)->get(route('insurance.workflow.claims'));
        $response->assertStatus(200);
        $response->assertSee('Insurance Claims Management');

        $response = $this->actingAs($admin)->get(route('insurance.workflow.fraud-evaluations'));
        $response->assertStatus(200);
        $response->assertSee('Actuarial Fraud');

        $response = $this->actingAs($admin)->get(route('insurance.workflow.parametric-triggers'));
        $response->assertStatus(200);
        $response->assertSee('Parametric Insurance');
    }
}
