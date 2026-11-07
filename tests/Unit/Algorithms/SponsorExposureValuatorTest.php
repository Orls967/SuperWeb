<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use InvalidArgumentException;
use Modules\Venue\Domain\Algorithms\SponsorExposureCertificate;
use Modules\Venue\Domain\Algorithms\SponsorExposureInput;
use Modules\Venue\Domain\Algorithms\SponsorExposureValuator;
use Modules\Venue\Domain\Algorithms\SponsorExposureVerifier;
use Tests\TestCase;

final class SponsorExposureValuatorTest extends TestCase
{
    private SponsorExposureValuator $valuator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->valuator = new SponsorExposureValuator;
    }

    public function test_weighted_impressions_and_full_payout(): void
    {
        // Footfall: [Main Stage 40.000, Beachfront 15.000, VIP 2.000]
        // Bobot dwell: [10.000 (1.0x), 6.000 (0.6x), 25.000 (2.5x)]
        // Screen share: 10.000 (100%)
        // Total impressions: 40.000 + 9.000 + 5.000 = 54.000
        // Target 50.000 -> 10.800 bps (> 100%) -> 100% payout
        // CPM Rp 60.000 -> Nilai eksposur = 54 * 60.000 = Rp 3.240.000
        $input = new SponsorExposureInput(
            zoneDeliveries: [
                ['zone_name' => 'Main Stage', 'footfall' => 40_000, 'dwell_weight_bps' => 10_000, 'screen_share_bps' => 10_000],
                ['zone_name' => 'Beachfront', 'footfall' => 15_000, 'dwell_weight_bps' => 6_000, 'screen_share_bps' => 10_000],
                ['zone_name' => 'VIP', 'footfall' => 2_000, 'dwell_weight_bps' => 25_000, 'screen_share_bps' => 10_000],
            ],
            targetImpressions: 50_000,
            cpmRateIdr: 60_000,
            milestoneBudgetAmountIdr: 100_000_000
        );

        $result = $this->valuator->valuate($input);

        $this->assertSame(54_000, $result->deliveredImpressions);
        $this->assertSame(3_240_000, $result->exposureValueIdr);
        $this->assertSame(10_800, $result->achievementBps);
        $this->assertSame(10_000, $result->payoutRatioBps);
        $this->assertSame(100_000_000, $result->milestonePayoutIdr);
        $this->assertTrue($result->certificate->isValid());
    }

    public function test_pro_rata_payout_and_under_delivery_penalty(): void
    {
        // Kasus 1: Capaian 80% (8.000 bps) -> Pro-rata payout 80%
        $inputProRata = new SponsorExposureInput(
            zoneDeliveries: [
                ['zone_name' => 'Main Stage', 'footfall' => 40_000, 'dwell_weight_bps' => 10_000, 'screen_share_bps' => 10_000],
            ],
            targetImpressions: 50_000,
            cpmRateIdr: 50_000,
            milestoneBudgetAmountIdr: 50_000_000
        );
        $resProRata = $this->valuator->valuate($inputProRata);
        $this->assertSame(40_000, $resProRata->deliveredImpressions);
        $this->assertSame(8_000, $resProRata->achievementBps);
        $this->assertSame(8_000, $resProRata->payoutRatioBps);
        $this->assertSame(40_000_000, $resProRata->milestonePayoutIdr);

        // Kasus 2: Capaian 60% (6.000 bps < 7.000 bps) -> 0% payout
        $inputUnder = new SponsorExposureInput(
            zoneDeliveries: [
                ['zone_name' => 'Main Stage', 'footfall' => 30_000, 'dwell_weight_bps' => 10_000, 'screen_share_bps' => 10_000],
            ],
            targetImpressions: 50_000,
            cpmRateIdr: 50_000,
            milestoneBudgetAmountIdr: 50_000_000
        );
        $resUnder = $this->valuator->valuate($inputUnder);
        $this->assertSame(30_000, $resUnder->deliveredImpressions);
        $this->assertSame(6_000, $resUnder->achievementBps);
        $this->assertSame(0, $resUnder->payoutRatioBps);
        $this->assertSame(0, $resUnder->milestonePayoutIdr);
    }

    public function test_tampered_sponsor_certificate_is_rejected(): void
    {
        $input = new SponsorExposureInput(
            zoneDeliveries: [
                ['zone_name' => 'Main Stage', 'footfall' => 50_000, 'dwell_weight_bps' => 10_000, 'screen_share_bps' => 10_000],
            ],
            targetImpressions: 50_000,
            cpmRateIdr: 50_000,
            milestoneBudgetAmountIdr: 10_000_000
        );

        $res = $this->valuator->valuate($input);

        $tampered = new SponsorExposureCertificate(
            deliveredImpressions: 50_000,
            targetImpressions: 50_000,
            achievementBps: 10_000,
            payoutRatioBps: 10_000,
            exposureValueIdr: 999_999_999, // dimanipulasi
            milestonePayoutIdr: 10_000_000,
            hmacSignature: $res->certificate->hmacSignature
        );

        $this->expectException(InvalidArgumentException::class);
        SponsorExposureVerifier::verify($tampered);
    }
}
