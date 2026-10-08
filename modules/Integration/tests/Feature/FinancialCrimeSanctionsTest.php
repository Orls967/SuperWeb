<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\FinancialCrimeSanctionsService;
use Tests\TestCase;

class FinancialCrimeSanctionsTest extends TestCase
{
    use RefreshDatabase;

    protected FinancialCrimeSanctionsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FinancialCrimeSanctionsService::class);
    }

    public function test_ubo_sanctions_chain_screening_and_operational_blocking(): void
    {
        // 1. Sanction parent holding company directly (273.1 & 273.4)
        $parent = $this->service->screenSanctionsWithUbo(
            entityCode: 'HOLDCO-SANCTIONED-01',
            entityName: 'Sanctioned Oligarch Holdings Ltd',
            parentEntityCode: null,
            uboOwnershipPct: 0.0,
            isDirectlySanctioned: true
        );
        $this->assertTrue((bool) $parent->operational_blocked);

        // 2. Subsidiary owned 75% by sanctioned parent -> OFAC 50% rule automatically blocks subsidiary (273.1 & 273.4)
        $subsidiary = $this->service->screenSanctionsWithUbo(
            entityCode: 'SUB-MINING-OPERATING',
            entityName: 'Nusantara Smelting Subsidiary Corp',
            parentEntityCode: 'HOLDCO-SANCTIONED-01',
            uboOwnershipPct: 75.0, // >= 50% UBO ownership!
            isDirectlySanctioned: false
        );

        $this->assertTrue((bool) $subsidiary->is_sanctioned_via_ubo);
        $this->assertTrue((bool) $subsidiary->operational_blocked);
        $this->assertGreaterThan(0.9, (float) $subsidiary->hit_confidence_score);
    }

    public function test_false_positive_screening_appeal_fast_track(): void
    {
        $entity = $this->service->screenSanctionsWithUbo('ENT-MISTAKEN', 'Similar Name LLC', null, 0.0, true);
        $this->assertTrue((bool) $entity->operational_blocked);

        // Appeal submitted and approved -> operational unblocked (273.5 Edge Case)
        $appealed = $this->service->appealSanctionsBlock('ENT-MISTAKEN', 'Verified distinct tax identification and registry jurisdiction');

        $this->assertTrue((bool) $appealed->is_appealed);
        $this->assertEquals('OVERTURNED_UNBLOCKED', $appealed->appeal_status);
        $this->assertFalse((bool) $appealed->operational_blocked);
    }

    public function test_trade_based_aml_evaluation(): void
    {
        // 1. Dual-use goods check flags suspicious AML (273.2)
        $dualUseTrade = $this->service->evaluateTradeAml(
            tradeRef: 'TRD-CENTRIFUGE-01',
            commodityCode: 'HIGH_GRADE_CARBON_FIBER',
            unitPriceUsd: 1000.0,
            benchmarkPriceUsd: 1000.0,
            isDualUse: true
        );
        $this->assertEquals('SUSPICIOUS_AML_FLAGGED', $dualUseTrade->risk_assessment);

        // 2. Severe pricing anomaly (> 30% deviation) flags suspicious AML (273.2)
        $anomalyTrade = $this->service->evaluateTradeAml(
            tradeRef: 'TRD-OVERPRICED-ORE',
            commodityCode: 'NICKEL_ORE',
            unitPriceUsd: 5000.0, // Benchmark is 2000 => 150% deviation!
            benchmarkPriceUsd: 2000.0
        );
        $this->assertEquals('SUSPICIOUS_AML_FLAGGED', $anomalyTrade->risk_assessment);
    }

    public function test_crypto_aml_wallet_screening_and_hold_blocking(): void
    {
        // 1. Clean exchange wallet allowed (273.3)
        $cleanWallet = $this->service->screenCryptoWallet('0xcleanexchange123', 'EXCHANGE', 1.5);
        $this->assertFalse((bool) $cleanWallet->is_hold_blocked);

        // 2. Mixer/Darknet wallet automatically blocked (273.3 & 273.4)
        $mixerWallet = $this->service->screenCryptoWallet('0xtornadocashpool', 'MIXER_TORNADO', 9.8);
        $this->assertTrue((bool) $mixerWallet->is_hold_blocked);
    }

    public function test_structuring_smurfing_detection(): void
    {
        // 4 transactions just below $10,000 threshold (e.g. $9,500, $9,200, $9,800, $9,100 => cumulative $37,600) (273.7)
        $alert = $this->service->evaluateStructuring(
            partyId: 'PTY-SUSPECT-SMURF',
            transactionAmountsUsd: [9500.0, 9200.0, 9800.0, 9100.0],
            reportingThresholdUsd: 10000.0
        );

        $this->assertTrue((bool) $alert->is_structuring_flagged);
        $this->assertEquals(4, (int) $alert->split_transaction_count);
        $this->assertEquals(37600.0, (float) $alert->cumulative_amount_usd);
    }

    public function test_financial_crime_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->screenSanctionsWithUbo('ENT-AUD', 'Audited', null, 0.0, false);
        $this->service->evaluateTradeAml('TRD-AUD', 'COMM', 10.0, 10.0);
        $this->service->screenCryptoWallet('0xaud', 'EXCHANGE', 1.0);
        $this->service->evaluateStructuring('PTY-AUD', [100.0]);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: high risk crypto wallet unblocked
        DB::table('fincrime_crypto_wallets')->insert([
            'wallet_address' => '0xunblockeddarknet',
            'cluster_category' => 'ILLICIT_DARKNET',
            'exposure_risk_score' => 9.5,
            'is_hold_blocked' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
