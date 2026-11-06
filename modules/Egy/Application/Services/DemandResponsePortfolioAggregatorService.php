<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Modules\Egy\Application\DTOs\DemandResponsePortfolioDTO;
use Modules\Egy\Domain\Models\Microgrid;

/**
 * Layanan Agregasi Portofolio Fleksibilitas Demand-Response Kawasan Terdistribusi.
 * Menghimpun beban yang dapat dipangkas cepat (< 15 menit) dari HVAC mall, pompa WTP,
 * dan stasiun pengisian daya EV untuk ditawarkan kepada program insentif PLN.
 */
final class DemandResponsePortfolioAggregatorService
{
    /**
     * Susun portofolio agregasi fleksibilitas demand-response.
     */
    public function aggregatePortfolio(string $portfolioCode): DemandResponsePortfolioDTO
    {
        $microgrids = Microgrid::with('feeders')->get();

        $totalFlexibleKw = 0;
        $totalBaselineKw = 0;
        $chillerHvacKw = 0;
        $pumpingWtpKw = 0;
        $evChargingKw = 0;
        $commercialLightingKw = 0;
        $participatingSites = [];

        foreach ($microgrids as $mg) {
            $siteTotalLoad = (int) $mg->current_load_kw;
            $totalBaselineKw += $siteTotalLoad;

            $siteFlexKw = 0;
            foreach ($mg->feeders as $feeder) {
                // Feeder Tier 3 (Commercial) dan Tier 4 (Flexible) dapat dipangkas
                if ($feeder->tier === 'TIER_4_FLEXIBLE') {
                    $flexAmount = (int) $feeder->rated_load_kw; // 100% EV charging dapat dipangkas
                    $evChargingKw += $flexAmount;
                    $siteFlexKw += $flexAmount;
                } elseif ($feeder->tier === 'TIER_3_COMMERCIAL') {
                    $flexAmount = (int) intdiv($feeder->rated_load_kw * 40, 100); // 40% HVAC mall dapat direduksi
                    $chillerHvacKw += $flexAmount;
                    $siteFlexKw += $flexAmount;
                }
            }

            $totalFlexibleKw += $siteFlexKw;
            $participatingSites[] = [
                'microgrid_code' => $mg->microgrid_code,
                'site_name' => $mg->site_name,
                'baseline_load_kw' => $siteTotalLoad,
                'flexible_curtailment_kw' => $siteFlexKw,
            ];
        }

        // Target curtailment cepat (80% dari total fleksibilitas teragregasi)
        $targetFastCurtailmentKw = (int) intdiv($totalFlexibleKw * 80, 100);

        // Potensi pendapatan insentif per jam (Rp 25.000 / kW terkurangi)
        $hourlyIncentiveRateIdr = 25000;
        $potentialIncentiveRevenueIdr = $targetFastCurtailmentKw * $hourlyIncentiveRateIdr;

        $hashPayload = sprintf(
            'DR-PORTFOLIO|CODE:%s|FLEX_KW:%d|BASE_KW:%d|FAST_KW:%d|POT_IDR:%d|SITES:%d',
            $portfolioCode,
            $totalFlexibleKw,
            $totalBaselineKw,
            $targetFastCurtailmentKw,
            $potentialIncentiveRevenueIdr,
            count($participatingSites)
        );
        $portfolioHash = hash('sha256', $hashPayload);

        return new DemandResponsePortfolioDTO(
            portfolioCode: $portfolioCode,
            totalAggregatedFlexibleCapacityKw: $totalFlexibleKw,
            totalCustomerBaselineLoadKw: $totalBaselineKw,
            targetFastCurtailmentKw: $targetFastCurtailmentKw,
            potentialHourlyIncentiveRevenueIdr: $potentialIncentiveRevenueIdr,
            flexibleLoadCategoriesKw: [
                'hvac_and_district_cooling_kw' => $chillerHvacKw,
                'ev_charging_infrastructure_kw' => $evChargingKw,
                'water_pumping_wtp_kw' => $pumpingWtpKw,
                'commercial_lighting_kw' => $commercialLightingKw,
            ],
            participatingSites: $participatingSites,
            portfolioHash: $portfolioHash
        );
    }
}
