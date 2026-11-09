<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use Modules\Hospital\Domain\Services\HospitalTariffMasterService;
use PHPUnit\Framework\TestCase;

class HospitalTariffMasterServiceTest extends TestCase
{
    private HospitalTariffMasterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HospitalTariffMasterService;
    }

    public function test_regional_tariff_calculation_applies_correct_multipliers(): void
    {
        $baseTariff = 10_000_000; // Rp 10.000.000

        // Zona 3 (Kalimantan & Sulawesi) = multiplier 1.12 -> Rp 11.200.000
        $adjusted = $this->service->adjustTariffForRegionalZone($baseTariff, 3);
        $this->assertSame(11_200_000, $adjusted);

        // Zona 5 (Papua) = multiplier 1.35 -> Rp 13.500.000
        $adjustedPapua = $this->service->adjustTariffForRegionalZone($baseTariff, 5);
        $this->assertSame(13_500_000, $adjustedPapua);
    }

    public function test_commercial_tariff_applies_corporate_discount_tiers(): void
    {
        // Tagihan tindakan Rp 20.000.000, farmasi Rp 5.000.000
        // Platinum tier: 15% pada tindakan medis, max diskon Rp 10.000.000, farmasi tidak didiskon
        $result = $this->service->applyCorporatePartnershipDiscount(
            'TIER_PLATINUM_INSURER',
            20_000_000,
            5_000_000
        );

        $this->assertSame('TIER_PLATINUM_INSURER', $result['tier_code']);
        // 15% dari 20jt = 3jt (< 10jt max)
        $this->assertSame(3_000_000, $result['applied_discount_idr']);
        // Net payable = 25jt - 3jt = 22jt
        $this->assertSame(22_000_000, $result['net_payable_charges_idr']);
        $this->assertFalse($result['is_charity_subsidized']);
    }

    public function test_community_charity_tier_includes_pharmacy(): void
    {
        // Charity: 35% pada tindakan + farmasi, max 25jt
        $result = $this->service->applyCorporatePartnershipDiscount(
            'COMMUNITY_HEALTH_CHARITY',
            10_000_000,
            2_000_000
        );

        $this->assertSame('COMMUNITY_HEALTH_CHARITY', $result['tier_code']);
        // 35% dari 12jt = 4.200.000
        $this->assertSame(4_200_000, $result['applied_discount_idr']);
        $this->assertSame(7_800_000, $result['net_payable_charges_idr']);
        $this->assertTrue($result['is_charity_subsidized']);
    }
}
