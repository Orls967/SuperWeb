<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Hotel\Domain\Algorithms\AwardNightPriceCertificateVerifier;
use Modules\Hotel\Domain\Algorithms\AwardNightPricer;
use Modules\Hotel\Domain\Algorithms\GlobalTierQualificationCertificateVerifier;
use Modules\Hotel\Domain\Algorithms\GlobalTierQualifier;
use Modules\Hotel\Domain\Algorithms\PartnerPointConversionCertificateVerifier;
use Modules\Hotel\Domain\Algorithms\PartnerPointConverter;
use Modules\Hotel\Domain\Algorithms\PointsLiabilityFifo;
use Modules\Hotel\Domain\Algorithms\PointsLiabilityFifoCertificateVerifier;
use Tests\TestCase;

class HotelAlgorithmsFase112Test extends TestCase
{
    /**
     * H10: PointsLiabilityFifo
     */
    public function test_h10_points_liability_fifo_prompt_vector(): void
    {
        $engine = new PointsLiabilityFifo;
        $verifier = new PointsLiabilityFifoCertificateVerifier;

        $batches = [
            [
                'batch_id' => 'BTH-2024-001',
                'remaining_pts' => 5000,
                'liability_per_pt_idr' => 100,
                'expires_at_timestamp' => 1700000000, // Expired if now is 1710000000
            ],
            [
                'batch_id' => 'BTH-2024-002',
                'remaining_pts' => 8000,
                'liability_per_pt_idr' => 100,
                'expires_at_timestamp' => 1750000000, // Active
            ],
            [
                'batch_id' => 'BTH-2024-003',
                'remaining_pts' => 12000,
                'liability_per_pt_idr' => 100,
                'expires_at_timestamp' => 1760000000, // Active
            ],
        ];

        // Current time: 1710000000. Batch 001 is expired. Batch 002 & 003 are active.
        // Redeem 10,000 points.
        $certificate = $engine->allocate(
            batches: $batches,
            ptsToRedeem: 10000,
            currentTimestamp: 1710000000
        );

        $this->assertTrue($verifier->verify($certificate));
        // Batch 001 expired: 5,000 pts * 100 IDR = 500,000 IDR breakage
        $this->assertSame(5000, $certificate->totalExpiredPts);
        $this->assertSame(500000, $certificate->totalBreakageRevenueIdr);

        // Redeem 10,000 pts from active batches:
        // Batch 002: takes all 8,000 pts (800,000 IDR)
        // Batch 003: takes 2,000 pts (200,000 IDR)
        $this->assertSame(10000, $certificate->totalPtsDeducted);
        $this->assertSame(1000000, $certificate->totalLiabilityDeductedIdr);
        $this->assertCount(2, $certificate->redemptionAllocations);
    }

    /**
     * H11: AwardNightPricer with 115% floor rule
     */
    public function test_h11_award_night_pricer_prompt_vector(): void
    {
        $engine = new AwardNightPricer;
        $verifier = new AwardNightPriceCertificateVerifier;

        // High occupancy (95%) with Gold tier (5% discount)
        $certPeak = $engine->calculate(
            basePointsRequired: 15000,
            occupancyRate: 0.95,
            seasonalityMultiplier: 1.5,
            marginalCostIdr: 400000, // 115% floor = 460,000 IDR -> 4,600 pts
            tier: 'GOLD',
            pointValuationIdr: 100
        );

        $this->assertTrue($verifier->verify($certPeak));
        $this->assertGreaterThan(15000, $certPeak->finalPointsRequired);
        $this->assertFalse($certPeak->isFloorApplied); // Demand price is way above floor

        // Low occupancy (10%) with Black tier (15% discount) and high marginal cost to test floor
        $certLow = $engine->calculate(
            basePointsRequired: 2000,
            occupancyRate: 0.10,
            seasonalityMultiplier: 1.0,
            marginalCostIdr: 300000, // 115% floor = 345,000 IDR -> 3,450 pts
            tier: 'BLACK',
            pointValuationIdr: 100
        );

        $this->assertTrue($verifier->verify($certLow));
        $this->assertTrue($certLow->isFloorApplied);
        // Guaranteed floor: ceil(300,000 * 1.15 / 100) = 3450 pts
        $this->assertSame(3450, $certLow->finalPointsRequired);
        $this->assertSame(345000, $certLow->reimbursementValueIdr);
    }

    /**
     * H12: PartnerPointConverter bilateral alliance netting
     */
    public function test_h12_partner_point_converter_prompt_vector(): void
    {
        $engine = new PartnerPointConverter;
        $verifier = new PartnerPointConversionCertificateVerifier;

        // Convert 10,000 Hotel PTS to Airline Miles
        // Exchange ratio: 10 PTS = 1 Mile (0.1000)
        // Transfer fee: 3.5%
        // Valuation: 100 IDR / pt -> total debited = 1,000,000 IDR
        $cert = $engine->convert(
            partnerCode: 'GARUDA_AIR',
            pointsToConvert: 10000,
            exchangeRatio: 10.0,
            transferFeePercent: 0.035,
            pointValuationIdr: 100
        );

        $this->assertTrue($verifier->verify($cert));
        $this->assertSame(1000, $cert->partnerUnitsCredited); // 1,000 Miles
        $this->assertSame(1000000, $cert->totalLiabilityDebitedIdr);
        $this->assertSame(35000, $cert->transferFeeIdr); // 3.5% of 1,000,000 IDR
        $this->assertSame(965000, $cert->netPayableToPartnerIdr); // 1,000,000 - 35,000
        $this->assertSame(
            $cert->totalLiabilityDebitedIdr,
            $cert->transferFeeIdr + $cert->netPayableToPartnerIdr
        );
    }

    /**
     * H13: GlobalTierQualifier multi-vector rolling qualification
     */
    public function test_h13_global_tier_qualifier_prompt_vector(): void
    {
        $engine = new GlobalTierQualifier;
        $verifier = new GlobalTierQualificationCertificateVerifier;

        // Case 1: Member qualifies for BLACK by spend (260M IDR) even if nights are low
        $certBlack = $engine->evaluate(
            rolling365Nights: 25,
            rolling365SpendIdr: 260000000,
            currentTier: 'GOLD',
            daysInGracePeriodRemaining: 0
        );

        $this->assertTrue($verifier->verify($certBlack));
        $this->assertSame('BLACK', $certBlack->qualifiedTier);
        $this->assertTrue($certBlack->isUpgraded);
        $this->assertFalse($certBlack->isDowngraded);

        // Case 2: Member fails Black renewal (only 30 nights, 50M spend) -> Soft-landing to PLATINUM
        $certSoftLanding = $engine->evaluate(
            rolling365Nights: 30,
            rolling365SpendIdr: 50000000,
            currentTier: 'BLACK',
            daysInGracePeriodRemaining: 0
        );

        $this->assertTrue($verifier->verify($certSoftLanding));
        $this->assertSame('PLATINUM', $certSoftLanding->qualifiedTier);
        $this->assertTrue($certSoftLanding->isDowngraded);
    }
}
