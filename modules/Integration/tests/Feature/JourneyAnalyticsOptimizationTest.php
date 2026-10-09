<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\JourneyAnalyticsOptimizationService;
use Tests\TestCase;

class JourneyAnalyticsOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected JourneyAnalyticsOptimizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(JourneyAnalyticsOptimizationService::class);
    }

    public function test_funnel_and_experiment_standardization_flow(): void
    {
        // 417.1 & 417.2 Cross-line journey funnel (Book Hotel -> Stay -> Dine at Padang Resto)
        $funnel = $this->service->recordFunnel(
            funnelCode: 'FNL-STAY-DINE-2026',
            journeyType: 'book_stay_dine',
            cohortPeriod: '2026-09',
            starts: 1000,
            s1: 850,
            s2: 800,
            s3: 640,
            version: 1
        );

        $this->assertEquals('FNL-STAY-DINE-2026', $funnel->funnel_code);
        $this->assertEquals(64.00, (float) $funnel->conversion_rate);

        // 417.3 Create friction reduction experiment
        $exp = $this->service->createExperiment(
            experimentCode: 'EXP-ONE-CLICK-DINE',
            funnelCode: 'FNL-STAY-DINE-2026',
            hypothesis: 'Presenting Padang dinner reservation voucher at hotel room check-in increases dining conversion',
            sampleSize: 500.00
        );

        $this->assertEquals('EXP-ONE-CLICK-DINE', $exp->experiment_code);

        // 417.4 & 417.6 Standardize with stat sig & journey-level regression checked
        $standardized = $this->service->standardizeExperiment(
            experimentCode: 'EXP-ONE-CLICK-DINE',
            statSigReached: true,
            journeyLevelChecked: true
        );

        $this->assertTrue((bool) $standardized->standardized);

        // Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_standardization_without_guardrails_blocked_edge_case(): void
    {
        $this->service->recordFunnel('FNL-COMMERCE', 'buy_deliver_return', '2026-09', 500, 400, 350, 300);
        $this->service->createExperiment('EXP-PREMATURE', 'FNL-COMMERCE', 'Fast checkout tweak', 100.00);

        // Missing statistical significance (417.4)
        try {
            $this->service->standardizeExperiment('EXP-PREMATURE', false, true);
            $this->fail('Expected exception for premature experiment standardization');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('has not reached required statistical significance', $e->getMessage());
        }

        // Missing journey level check (417.6)
        try {
            $this->service->standardizeExperiment('EXP-PREMATURE', true, false);
            $this->fail('Expected exception for missing journey regression check');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Missing mandatory cross-step journey regression impact check', $e->getMessage());
        }
    }
}
