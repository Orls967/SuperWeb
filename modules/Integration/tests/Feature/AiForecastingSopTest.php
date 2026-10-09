<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\AiForecastingSopService;
use Tests\TestCase;

/**
 * Fase 201 — AI: Forecasting Federation & S&OP 30 Lini Tests
 *
 * Covers:
 *  (a) hierarchical forecast enforces bottom-up == national aggregate parity
 *  (b) forecast discrepancy throws runtime exception
 *  (c) executive S&OP balancing sign-off requires authorized officer
 *  (d) tower:audit = 0 discrepancy
 */
class AiForecastingSopTest extends TestCase
{
    use RefreshDatabase;

    protected AiForecastingSopService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AiForecastingSopService::class);
    }

    /**
     * (a) & (b) Hierarchical forecast reconciliation parity.
     */
    public function test_hierarchical_forecast_reconciliation(): void
    {
        // 1. Balanced: National 100,000 units == Regional sum (40k + 60k = 100k) -> SUCCESS
        $fcst = $this->service->registerForecast('L13_RETAIL', '2026-Q4', 100000.0, 100000.0, 3.8);
        $this->assertSame('2026-Q4', $fcst->forecast_period);
        $this->assertEquals(0.00, (float) $fcst->reconciliation_discrepancy);

        // 2. Discrepancy: National 100,000 units != Regional sum 95,000 units -> Exception
        try {
            $this->service->registerForecast('L13_RETAIL', '2026-Q4', 100000.0, 95000.0, 3.8);
            $this->fail('Expected exception for forecast reconciliation discrepancy.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Hierarchical forecast discrepancy', $e->getMessage());
        }
    }

    /**
     * (c) Executive S&OP sign-off.
     */
    public function test_executive_sop_signoff(): void
    {
        $signoff = $this->service->executeExecutiveSignoff('2026-Q4', 100000.0, 105000.0, 'Direktur Operasional Grup');
        $this->assertSame('APPROVED', $signoff->status);
        $this->assertSame('Direktur Operasional Grup', $signoff->signed_off_by);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_forecasting_sop_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
