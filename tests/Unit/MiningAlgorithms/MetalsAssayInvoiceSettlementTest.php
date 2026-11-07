<?php

declare(strict_types=1);

namespace Tests\Unit\MiningAlgorithms;

use Modules\Shared\Domain\Algorithms\MetalsTrading\AssaySplitComparison;
use Modules\Shared\Domain\Algorithms\MetalsTrading\MetalsAssayInvoiceSettlementCalculator;
use Modules\Shared\Domain\Algorithms\MetalsTrading\MetalsAssayInvoiceSettlementVerifier;
use Tests\TestCase;

final class MetalsAssayInvoiceSettlementTest extends TestCase
{
    public function test_commercial_assay_invoice_settlement_with_umpire_dispute(): void
    {
        $calculator = new MetalsAssayInvoiceSettlementCalculator;
        $verifier = new MetalsAssayInvoiceSettlementVerifier;

        $input = new AssaySplitComparison(
            lotNetWeightTons: 100.0,
            sellerNiGradePct: 12.0,
            buyerNiGradePct: 11.4,
            splittingLimitPct: 0.15,
            umpireNiGradePct: 11.8,
            lmeOfficialPriceUsdPerTon: 18000,
            provisionalPaymentRateBps: 8500,
            exchangeRateIdrPerUsd: 16000,
            umpireServiceFeeIdr: 50000000
        );

        $result = $calculator->calculate($input);

        $this->assertEquals(11.8, $result->agreedFinalNiGradePct);
        $this->assertTrue($result->isDisputeTriggered);
        $this->assertTrue($result->isUmpireResolved);
        $this->assertEquals('BUYER', $result->umpireFeeBearingParty);
        $this->assertGreaterThan(0, $result->settlementDifferentialIdr);
        $this->assertEquals(50000000, $result->buyerUmpireCostAllocationIdr);

        $this->assertTrue($verifier->verify($result, $input));
    }
}
