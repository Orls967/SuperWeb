<?php

declare(strict_types=1);

namespace Modules\Partner\tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Partner\Application\Actions\AuditIpTerritoryLicensingAction;
use Modules\Partner\Application\Actions\CalculateJvEquityProfitShareAction;
use Modules\Partner\Application\Actions\SettlePartnerMilestoneCoDevAction;
use Modules\Partner\Domain\Models\IntellectualProperty;
use Modules\Partner\Domain\Models\JointPlan;
use Modules\Partner\Domain\Models\Partner;
use Tests\TestCase;

class PartnerOperationsSliceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->user = User::first() ?? User::factory()->create(['role' => 'admin']);

        $this->partner = Partner::create([
            'code' => 'PTN-TEST-001',
            'name' => 'PT Global Biotech Partner',
            'kind' => 'jv',
            'status' => 'active',
            'risk_score' => 15,
        ]);
    }

    public function test_calculate_jv_equity_profit_share_accrues_correct_integer_amount(): void
    {
        $action = app(CalculateJvEquityProfitShareAction::class);

        // Gross 1,000,000,000 - Deductible 400,000,000 = Net 600,000,000 * 30% = 180,000,000
        $res = $action->execute(
            partner: $this->partner,
            period: '2026-Q3',
            grossRevenueIdr: 1000000000,
            deductibleCostIdr: 400000000,
            shareRatePercent: 30.0
        );

        $this->assertSame(600000000, $res['net_base_idr']);
        $this->assertSame(180000000, $res['share_amount_idr']);
        $this->assertSame('accrued', $res['revenue_share']->status);
    }

    public function test_calculate_jv_equity_profit_share_validates_percentage_bounds(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $action = app(CalculateJvEquityProfitShareAction::class);
        $action->execute(
            partner: $this->partner,
            period: '2026-Q3',
            grossRevenueIdr: 100000000,
            deductibleCostIdr: 20000000,
            shareRatePercent: 120.0 // > 100% invalid
        );
    }

    public function test_settle_partner_milestone_codev_updates_kpis_and_status(): void
    {
        $plan = JointPlan::create([
            'partner_id' => (string) $this->partner->id,
            'title' => 'Joint R&D Vaccine Formulation',
            'period' => '2026',
            'budget_idr' => 500000000,
            'target_revenue_idr' => 2000000000,
            'status' => 'active',
            'kpi_targets' => ['phase1' => ['deliverable' => 'Prototype A']],
        ]);

        $action = app(SettlePartnerMilestoneCoDevAction::class);
        $res = $action->execute(
            jointPlan: $plan,
            milestoneName: 'phase1',
            milestonePayoutIdr: 150000000,
            verifiedKpis: ['quality_score' => 98]
        );

        $this->assertSame('milestone_settled', $res['status']);
        $this->assertSame(150000000, $res['released_payout_idr']);
        $this->assertTrue($res['joint_plan']->kpi_targets['phase1']['is_settled']);
    }

    public function test_settle_partner_milestone_codev_blocks_exceeding_budget(): void
    {
        $plan = JointPlan::create([
            'partner_id' => (string) $this->partner->id,
            'title' => 'Small Joint Study',
            'period' => '2026',
            'budget_idr' => 100000000,
            'target_revenue_idr' => 300000000,
            'status' => 'active',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $action = app(SettlePartnerMilestoneCoDevAction::class);
        $action->execute(
            jointPlan: $plan,
            milestoneName: 'over_budget_milestone',
            milestonePayoutIdr: 150000000 // > 100M budget
        );
    }

    public function test_audit_ip_territory_licensing_detects_expiring_and_expired(): void
    {
        IntellectualProperty::create([
            'partner_id' => (string) $this->partner->id,
            'type' => 'patent',
            'registration_number' => 'IDP-2024-001',
            'name' => 'Cold-chain formulation stabilization method',
            'registered_at' => now()->subYears(2)->toDateString(),
            'expires_at' => now()->addDays(20)->toDateString(), // Expiring in 20 days
            'status' => 'active',
        ]);

        IntellectualProperty::create([
            'partner_id' => (string) $this->partner->id,
            'type' => 'trademark',
            'registration_number' => 'IDM-2020-002',
            'name' => 'BioNanoGuard Brand',
            'registered_at' => now()->subYears(5)->toDateString(),
            'expires_at' => now()->subDays(10)->toDateString(), // Already expired
            'status' => 'active',
        ]);

        $action = app(AuditIpTerritoryLicensingAction::class);
        $audit = $action->execute($this->partner, warningWindowDays: 60);

        $this->assertSame(2, $audit['total_ip']);
        $this->assertSame(1, $audit['expired_count']);
        $this->assertSame(1, $audit['expiring_soon_count']);
        $this->assertSame('non_compliant_expired_licenses', $audit['compliance_status']);
    }

    public function test_partner_governance_controller_endpoints(): void
    {
        $this->actingAs($this->user);

        $plan = JointPlan::create([
            'partner_id' => (string) $this->partner->id,
            'title' => 'Joint Cloud API Gateway',
            'period' => '2026',
            'budget_idr' => 200000000,
            'status' => 'active',
        ]);

        $respProfit = $this->postJson("/partner/governance/{$this->partner->id}/profit-share", [
            'period' => '2026-Q3',
            'gross_revenue_idr' => 500000000,
            'deductible_cost_idr' => 100000000,
            'share_rate_percent' => 25.0,
        ]);
        $respProfit->assertOk();
        $respProfit->assertJsonFragment(['share_amount_idr' => 100000000]);

        $respMilestone = $this->postJson("/partner/governance/joint-plans/{$plan->id}/milestone", [
            'milestone_name' => 'beta_release',
            'payout_idr' => 50000000,
        ]);
        $respMilestone->assertOk();
        $respMilestone->assertJsonFragment(['released_payout_idr' => 50000000]);
    }
}
