<?php

declare(strict_types=1);

namespace Tests\Unit\MiningAlgorithms;

use Modules\Shared\Domain\Algorithms\MetalsTrading\WarehouseReceiptDigitalCollateralHaircutModel;
use Modules\Shared\Domain\Algorithms\MetalsTrading\WarehouseReceiptDigitalCollateralHaircutVerifier;
use Tests\TestCase;

class WarehouseReceiptHaircutTest extends TestCase
{
    public function test_warehouse_receipt_haircut_computes_ltv_and_credit_limit(): void
    {
        $model = new WarehouseReceiptDigitalCollateralHaircutModel;
        $verifier = new WarehouseReceiptDigitalCollateralHaircutVerifier;

        $res = $model->calculateHaircut(
            metalCommodity: 'NPI',
            certifiedWeightMetricTons: 100.0,
            unitMarketPriceIdrPerTon: 240000000,
            historicalVolatility30DayPct: 20.0,
            storageAgeDays: 10,
            assayVarianceTolerancePct: 0.3,
            bankLtvCapPct: 80.0
        );

        $this->assertEquals(24000000000, $res->grossCollateralValueIdr);
        $this->assertGreaterThanOrEqual(10.0, $res->totalHaircutPct);
        $this->assertLessThanOrEqual(50.0, $res->totalHaircutPct);
        $this->assertEquals(round(100.0 - $res->totalHaircutPct, 2), $res->effectiveLtvPct);
        $this->assertGreaterThan(0, $res->maxFinancingCreditLimitIdr);
        $this->assertTrue($res->isCollateralApproved);
        $this->assertNotEmpty($res->digitalSealSignature);

        // Verifier
        $this->assertTrue($verifier->verify($res));
    }
}
