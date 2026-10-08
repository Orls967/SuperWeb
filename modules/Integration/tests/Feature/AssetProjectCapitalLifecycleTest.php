<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\AssetProjectCapitalLifecycleService;
use Tests\TestCase;

class AssetProjectCapitalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected AssetProjectCapitalLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AssetProjectCapitalLifecycleService::class);
    }

    public function test_cip_capitalization_and_double_capitalization_prevention(): void
    {
        // 1. Capitalize asset from project CIP (377.1 & 377.4)
        $asset = $this->service->capitalizeProjectAsset(
            assetCode: 'AST-DRY-DOCK-BERTH-01',
            projectCode: 'PRJ-PORT-EXPANSION-2026',
            capitalizedCostUsd: 45000000.00
        );
        $this->assertEquals(45000000.00, $asset->capitalized_cost_usd);
        $this->assertTrue((bool) $asset->cip_reconciled);
        $this->assertTrue((bool) $asset->double_capitalization_prevented);

        // 2. Attempting double capitalization of same asset fails (377.4 & 377.6 Risk)
        try {
            $this->service->capitalizeProjectAsset(
                assetCode: 'AST-DRY-DOCK-BERTH-01',
                projectCode: 'PRJ-PORT-EXPANSION-2026',
                capitalizedCostUsd: 45000000.00
            );
            $this->fail('Expected exception for double capitalization');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('double capitalization strictly prevented', $e->getMessage());
        }
    }

    public function test_post_investment_review_benefit_failure_learning_edge_case(): void
    {
        // 1. Failed benefit realization (< 50%) without learning action items fails (377.4 & 377.5 Edge Case)
        try {
            $this->service->conductPostInvestmentReview(
                reviewCode: 'REV-BENEFIT-001',
                projectCode: 'PRJ-SOLAR-FARM-01',
                expectedBenefitUsd: 1000000.00,
                actualBenefitUsd: 300000.00, // 30% realization (< 50%)
                learningActionItems: null // Missing learning!
            );
            $this->fail('Expected exception for undocumented failed post-investment benefit review');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requiring documented organizational learning action items', $e->getMessage());
        }

        // 2. Failed benefit realization with documented learning actions succeeds (377.5)
        $review = $this->service->conductPostInvestmentReview(
            reviewCode: 'REV-BENEFIT-002',
            projectCode: 'PRJ-SOLAR-FARM-01',
            expectedBenefitUsd: 1000000.00,
            actualBenefitUsd: 300000.00,
            learningActionItems: 'Panel degradation under tropical humidity higher than modeled; switch supplier to marine-grade PV.'
        );
        $this->assertTrue((bool) $review->benefit_realization_failed);
        $this->assertNotNull($review->learning_action_items);
    }

    public function test_ast_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->capitalizeProjectAsset('AST-AUD', 'PRJ-1', 100000.00);
        $this->service->conductPostInvestmentReview('REV-AUD', 'PRJ-1', 100000.00, 120000.00, null);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unreconciled CIP
        DB::table('global_capital_project_assets')->insert([
            'asset_code' => 'AST-DEFECT-UNRECONCILED',
            'project_code' => 'PRJ-1',
            'capitalized_cost_usd' => 50000.00,
            'cip_reconciled' => false, // Discrepancy!
            'double_capitalization_prevented' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
