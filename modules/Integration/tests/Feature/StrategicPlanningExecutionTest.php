<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\StrategicPlanningExecutionService;
use Tests\TestCase;

class StrategicPlanningExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected StrategicPlanningExecutionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StrategicPlanningExecutionService::class);
    }

    public function test_strategy_tree_cascade_and_quarterly_review_flow(): void
    {
        // 454.1 Build Strategy Tree
        // Vision node
        $vision = $this->service->registerStrategyNode(
            version: '2026-V1',
            nodeCode: 'VIS-2030',
            nodeType: 'vision',
            title: 'Leading Sustainable Mobility & Logistics Ecosystem in ASEAN',
            parentNodeCode: null,
            owner: 'Board of Directors'
        );
        $this->assertEquals('VIS-2030', $vision->node_code);

        // Strategic Theme linked to Vision
        $theme = $this->service->registerStrategyNode(
            version: '2026-V1',
            nodeCode: 'THM-GREEN-FLEET',
            nodeType: 'theme',
            title: '100% Zero-Emission Fleet Electrification',
            parentNodeCode: 'VIS-2030',
            owner: 'Chief Executive Officer'
        );
        $this->assertEquals('VIS-2030', $theme->parent_node_code);

        // Objective linked to Theme
        $obj = $this->service->registerStrategyNode(
            version: '2026-V1',
            nodeCode: 'OBJ-EV-INFRA',
            nodeType: 'objective',
            title: 'Deploy 50 Fast Charging Hubs across Java-Sumatra Corridor',
            parentNodeCode: 'THM-GREEN-FLEET',
            owner: 'VP Infrastructure',
            funding: 50000000000.00
        );
        $this->assertEquals('THM-GREEN-FLEET', $obj->parent_node_code);

        // 454.3 Record Quarterly review with budget reallocation
        $review = $this->service->recordQuarterlyReview(
            reviewCode: 'REV-2026-Q2',
            quarter: '2026-Q2',
            version: '2026-V1',
            reallocatedFunding: 5000000000.00
        );
        $this->assertTrue((bool) $review->board_strategy_report_approved);

        // 454.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_orphaned_cascade_node_and_strategy_pivot_edge_cases(): void
    {
        // 454.6 Risk: Unlinked non-vision node is blocked
        try {
            $this->service->registerStrategyNode(
                version: '2026-V1',
                nodeCode: 'ORPHAN-INITIATIVE',
                nodeType: 'initiative',
                title: 'Pet project without strategy alignment',
                parentNodeCode: null, // Missing parent!
                owner: 'Rogue Manager'
            );
            $this->fail('Expected exception for unlinked non-vision strategy node');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('must link upward to an active parent node', $e->getMessage());
        }

        // 454.5 Edge case: Mid-year strategy pivot cleanly deactivates previous version
        $this->service->registerStrategyNode('2026-V1', 'VIS-OLD', 'vision', 'Old Vision', null, 'CEO');
        $deactivatedCount = $this->service->pivotStrategyVersion('2026-V1', '2026-V2');
        $this->assertGreaterThan(0, $deactivatedCount);

        // Register new version nodes
        $this->service->registerStrategyNode('2026-V2', 'VIS-NEW', 'vision', 'New Pivoted Vision', null, 'CEO');

        // Audit verifies single active version
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(1, $audit['active_versions_count']);
    }
}
