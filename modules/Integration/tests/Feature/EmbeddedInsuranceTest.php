<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\EmbeddedInsuranceService;
use Tests\TestCase;

/**
 * Fase 158 — Embedded Insurance Tests
 *
 * Covers:
 *  (a) embedded offer muncul di konteks lini bisnis yang sesuai
 *  (b) parametric payout = parameter terukur (curah hujan / pembatalan)
 *  (c) komisi broker = rate × premium
 *  (d) fraud score tinggi → hold SIU investigation / denial
 *  (e) ins:audit lintas lini = 0 selisih
 */
class EmbeddedInsuranceTest extends TestCase
{
    use RefreshDatabase;

    protected EmbeddedInsuranceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EmbeddedInsuranceService::class);
    }

    /**
     * (a) Embedded products registered across multiple business lines.
     */
    public function test_embedded_products_catalog(): void
    {
        $hotelCancel = $this->service->registerEmbeddedProduct('HTL', 'Hotel Free Cancellation Cover', 50000.0, 1500000.0);
        $cargoLoss = $this->service->registerEmbeddedProduct('LOG', 'Freight Damage & Spoilage Protection', 120000.0, 50000000.0);

        $this->assertSame('HTL', $hotelCancel->line_code);
        $this->assertSame('LOG', $cargoLoss->line_code);
        $this->assertTrue((bool) $hotelCancel->is_active);
    }

    /**
     * (b) Parametric trigger triggers instant payout when condition met.
     */
    public function test_parametric_trigger_evaluation(): void
    {
        // Drought index: threshold = 50mm, actual = 20mm -> triggered payout Rp 10,000,000
        $drought = $this->service->evaluateParametricTrigger('RAINFALL_DROUGHT', 50.0, 20.0, 10000000.00);
        $this->assertTrue((bool) $drought->is_triggered);
        $this->assertEquals(10000000.00, (float) $drought->payout_per_policy);

        // Normal rainfall: threshold = 50mm, actual = 80mm -> not triggered
        $normal = $this->service->evaluateParametricTrigger('RAINFALL_DROUGHT', 50.0, 80.0, 10000000.00);
        $this->assertFalse((bool) $normal->is_triggered);
        $this->assertEquals(0.00, (float) $normal->payout_per_policy);
    }

    /**
     * (c) Broker commission accurately calculated.
     */
    public function test_broker_commission_settlement(): void
    {
        // Gross premium: 10,000,000. Rate: 12.5%. Commission = 1,250,000.
        $comm = $this->service->settleBrokerCommission('BRK-MARSH-01', 'POL-CARGO-123', 10000000.00, 12.5);

        $this->assertEquals(1250000.00, (float) $comm->commission_amount);
        $this->assertSame('PAID', $comm->status);
    }

    /**
     * (d) Fraud score threshold triggers SIU hold.
     */
    public function test_fraud_score_triggers_siu_investigation(): void
    {
        // Normal claim (score 20) -> APPROVED
        $clean = $this->service->assessFraudRisk('CLM-NORMAL-01', 20);
        $this->assertSame('APPROVED', $clean->decision);
        $this->assertFalse((bool) $clean->is_held_for_siu);

        // Suspicious claim (score 80) -> SIU_INVESTIGATION & held
        $suspicious = $this->service->assessFraudRisk('CLM-SUSPICIOUS-02', 80, 'Contradictory telematics GPS data');
        $this->assertSame('SIU_INVESTIGATION', $suspicious->decision);
        $this->assertTrue((bool) $suspicious->is_held_for_siu);

        // Obvious fraud (score 95) -> DENIED
        $fraud = $this->service->assessFraudRisk('CLM-FRAUD-03', 95, 'Prior duplicate loss history across multiple accounts');
        $this->assertSame('DENIED', $fraud->decision);
        $this->assertTrue((bool) $fraud->is_held_for_siu);
    }

    /**
     * (e) Audit status healthy.
     */
    public function test_embedded_insurance_audit(): void
    {
        $this->service->settleBrokerCommission('BRK-AON-02', 'POL-PROP-888', 5000000.00, 10.0);

        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
