<?php

declare(strict_types=1);

namespace Modules\Insurance\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Insurance\Application\Services\UniversalCrossLineInsuranceClaimService;
use Modules\Insurance\Domain\Models\InsuranceClaim;
use Modules\Insurance\Domain\Models\InsurancePolicy;
use Modules\Insurance\Domain\Models\InsuranceProduct;
use Tests\TestCase;

class UniversalCrossLineInsuranceClaimTest extends TestCase
{
    use RefreshDatabase;

    protected UniversalCrossLineInsuranceClaimService $claimService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->claimService = app(UniversalCrossLineInsuranceClaimService::class);
    }

    public function test_universal_parametric_claim_autopilot_across_12_lines(): void
    {
        $user = User::factory()->create();
        $product = InsuranceProduct::create([
            'product_code' => 'PARAMETRIC-MULTI-LINE',
            'name' => '12-Line Parametric Breach Policy',
            'category' => 'PARAMETRIC',
            'trigger_event_type' => 'COLD_CHAIN_BREACH',
            'max_payout_idr' => 100_000_000,
            'premium_amount_idr' => 2_500_000,
            'is_active' => true,
        ]);

        $policy = InsurancePolicy::create([
            'policy_number' => 'POL-MULTI-2026-001',
            'product_id' => $product->id,
            'user_id' => $user->id,
            'subject_ref_type' => 'LOGISTICS_SHIPMENT',
            'subject_ref_id' => 'SHP-9988',
            'premium_paid_idr' => 2_500_000,
            'coverage_limit_idr' => 100_000_000,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'status' => 'ACTIVE',
        ]);

        // 1. Cold-chain breach trigger (Severity 50 -> 50% of 100M = 50M IDR)
        $result = $this->claimService->executeParametricClaim(
            policyNumber: 'POL-MULTI-2026-001',
            triggerTypeCode: 'COLD_CHAIN_BREACH',
            severityScore: 50,
            telemetryProofHash: hash('sha256', 'temp_sensor:8.5C:45min'),
            claimantId: (string) $user->id
        );

        $this->assertEquals('DISBURSED_AUTOPILOT', $result['status']);
        $this->assertEquals(50_000_000, $result['payout_idr']);
        $this->assertEquals('POL-MULTI-2026-001', $result['policy_number']);

        // Verify Insurance Claim record
        $claim = InsuranceClaim::where('policy_id', $policy->id)->firstOrFail();
        $this->assertEquals('paid', $claim->status);
        $this->assertEquals(50_000_000, $claim->payout_amount_idr);

        // Verify Ledger transaction double-entry balance
        $tx = LedgerTransaction::where('reference_type', 'insurance_policies')
            ->where('reference_id', (string) $policy->id)
            ->firstOrFail();

        $this->assertEquals('insurance_claim_payout', $tx->type);
        $sum = 0;
        foreach ($tx->entries as $entry) {
            $sum += (int) (string) $entry->amount;
        }
        $this->assertSame(0, $sum, 'Double entry ledger must balance to exact zero');
    }

    public function test_parametric_claim_capped_at_policy_coverage_limit(): void
    {
        $user = User::factory()->create();
        $product = InsuranceProduct::create([
            'product_code' => 'MINE-WEATHER-PRO',
            'name' => 'Mining Tropical Storm Downtime',
            'category' => 'PARAMETRIC',
            'trigger_event_type' => 'WEATHER_BREACH',
            'max_payout_idr' => 20_000_000,
            'premium_amount_idr' => 1_000_000,
            'is_active' => true,
        ]);

        $policy = InsurancePolicy::create([
            'policy_number' => 'POL-MINE-STORM-77',
            'product_id' => $product->id,
            'user_id' => $user->id,
            'subject_ref_type' => 'MINING_SITE',
            'subject_ref_id' => 'SITE-KOLAKA-01',
            'premium_paid_idr' => 1_000_000,
            'coverage_limit_idr' => 20_000_000,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'status' => 'ACTIVE',
        ]);

        // Severe weather with severity 150% -> Payout must be capped at 20,000,000 IDR
        $result = $this->claimService->executeParametricClaim(
            policyNumber: 'POL-MINE-STORM-77',
            triggerTypeCode: 'WEATHER_BREACH',
            severityScore: 150,
            telemetryProofHash: hash('sha256', 'rain_gauge:95mm:3hrs'),
            claimantId: (string) $user->id
        );

        $this->assertEquals(20_000_000, $result['payout_idr']);
    }
}
