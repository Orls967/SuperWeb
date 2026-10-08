<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\DigitalProductExperimentationService;
use Tests\TestCase;

/**
 * Fase 236 — Inovasi: Digital Product Factory & Experimentation Tests
 *
 * Covers:
 *  (a) Feature flags gradual rollout and instant real-time kill switch
 *  (b) Deterministic A/B testing variant hash allocation per user
 *  (c) Edge Case 236.6: Critical metric regression (>10% drop) auto-kills experiment and linked flag
 *  (d) Statistical significance decisioning (p-value < 0.05)
 *  (e) Quality audit platform:audit clean with 0 discrepancies
 */
class DigitalProductExperimentationTest extends TestCase
{
    use RefreshDatabase;

    protected DigitalProductExperimentationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DigitalProductExperimentationService::class);
    }

    /**
     * (a) Feature flag gradual rollout and instant kill switch (236.2 & 236.5).
     */
    public function test_feature_flag_rollout_and_kill_switch(): void
    {
        $flag = $this->service->createFeatureFlag('FEAT-ONE-CLICK-PAY', 'FINTECH', 'Express one click payment', 100, true);

        // Active with 100% rollout -> true
        $this->assertTrue($this->service->isFeatureEnabled('FEAT-ONE-CLICK-PAY', 'USER-101'));

        // Real-time kill switch triggered
        $this->service->killFeatureFlag('FEAT-ONE-CLICK-PAY', 'Spike in gateway transaction timeouts');

        // Immediately returns false for all users
        $this->assertFalse($this->service->isFeatureEnabled('FEAT-ONE-CLICK-PAY', 'USER-101'));
        $this->assertFalse($this->service->isFeatureEnabled('FEAT-ONE-CLICK-PAY', 'USER-102'));
    }

    /**
     * (b) Deterministic variant allocation (236.3 & 236.5).
     */
    public function test_deterministic_variant_allocation(): void
    {
        $this->service->createFeatureFlag('FEAT-NEW-UI', 'RETAIL', 'New product grid', 50, true);
        $this->service->createExperiment('EXP-GRID-TEST', 'FEAT-NEW-UI', 'Grid increases checkout CTR', 'CTR');

        $variantFirst = $this->service->assignVariant('EXP-GRID-TEST', 'USER-CONST-1');
        $variantSecond = $this->service->assignVariant('EXP-GRID-TEST', 'USER-CONST-1');

        $this->assertSame($variantFirst, $variantSecond);
        $this->assertContains($variantFirst, ['CONTROL', 'VARIANT_B']);
    }

    /**
     * (c) Edge Case 236.6: Critical conversion drop > 10% auto-kills experiment & flag.
     */
    public function test_critical_metric_regression_auto_kill(): void
    {
        $this->service->createFeatureFlag('FEAT-CHECKOUT-V3', 'RETAIL', 'Experimental checkout step', 50, true);
        $this->service->createExperiment('EXP-CHECKOUT-3', 'FEAT-CHECKOUT-V3', 'Faster checkout', 'CONVERSION_RATE');

        // Control: 5.0% conversion, Variant: 4.0% conversion (20% drop > 10% threshold) -> Auto-kill!
        $result = $this->service->evaluateExperimentResults('EXP-CHECKOUT-3', 5.0, 4.0, 0.01);

        $this->assertSame('AUTO_KILLED_REGRESSION', $result->status);
        $this->assertStringContainsString('Auto-killed', $result->auto_killed_reason);

        // Verify linked feature flag was also killed
        $this->assertFalse($this->service->isFeatureEnabled('FEAT-CHECKOUT-V3', 'ANY_USER'));
    }

    /**
     * (d) Statistically significant positive experiment rollout (236.3 & 236.5).
     */
    public function test_positive_experiment_rollout(): void
    {
        $this->service->createFeatureFlag('FEAT-SEARCH-AI', 'MEDIA', 'AI semantic search', 50, true);
        $this->service->createExperiment('EXP-SEARCH-AI', 'FEAT-SEARCH-AI', 'AI search increases engagement', 'ENGAGEMENT');

        // Control: 10.0%, Variant: 14.5%, p-value: 0.02 (< 0.05) -> Significant -> Rollout
        $result = $this->service->evaluateExperimentResults('EXP-SEARCH-AI', 10.0, 14.5, 0.02);

        $this->assertSame('COMPLETED_ROLLOUT', $result->status);
        $this->assertTrue((bool) $result->is_statistically_significant);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_experimentation_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
