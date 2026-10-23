<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Models\EntertainmentVenue;

class OptimizeGlobalHospitalityEntertainmentPortfolioRevparAction extends BaseAction
{
    /**
     * Compute cross-network revenue command metrics: RevPAR = ADR * Occupancy%, GMV consolidation and FX exposure.
     *
     * @return array{region_city: string, year: int, month: int, adr_idr: int, occupancy_pct: float, revpar_idr: int, venue_ticket_gmv_idr: int, travel_bundle_gmv_idr: int, total_gmv_idr: int, total_gmv_usd: int}
     */
    public function handle(
        string $regionCity,
        int $year,
        int $month,
        int $adrIdr,
        float $occupancyPercent,
        int $venueTicketGmvIdr,
        int $travelBundleGmvIdr,
        int $fxRateUsdToIdr = 16000
    ): array {
        return DB::transaction(function () use (
            $regionCity,
            $year,
            $month,
            $adrIdr,
            $occupancyPercent,
            $venueTicketGmvIdr,
            $travelBundleGmvIdr,
            $fxRateUsdToIdr
        ) {
            // 118.1 & 118.5 (a) RevPAR = ADR * Occupancy%
            $revparIdr = (int) round(($adrIdr * $occupancyPercent) / 100.0);
            $totalGmvIdr = $venueTicketGmvIdr + $travelBundleGmvIdr;

            // 118.4 Multi-country FX exposure translation in USD
            $totalGmvUsd = ($fxRateUsdToIdr > 0) ? (int) round($totalGmvIdr / $fxRateUsdToIdr) : 0;

            $activeVenuesCount = EntertainmentVenue::where('city', $regionCity)->count();

            $this->audit(
                action: 'venue.revenue_command.portfolio_optimized',
                auditable: null,
                oldValues: null,
                newValues: [
                    'region_city' => $regionCity,
                    'year' => $year,
                    'month' => $month,
                    'adr_idr' => $adrIdr,
                    'occupancy_pct' => $occupancyPercent,
                    'revpar_idr' => $revparIdr,
                    'total_gmv_idr' => $totalGmvIdr,
                    'total_gmv_usd' => $totalGmvUsd,
                    'active_venues_count' => $activeVenuesCount,
                ],
                correlationId: "ven_revpar_{$regionCity}_{$year}{$month}",
                impactType: 'portfolio_revenue_optimization'
            );

            return [
                'region_city' => $regionCity,
                'year' => $year,
                'month' => $month,
                'adr_idr' => $adrIdr,
                'occupancy_pct' => $occupancyPercent,
                'revpar_idr' => $revparIdr,
                'venue_ticket_gmv_idr' => $venueTicketGmvIdr,
                'travel_bundle_gmv_idr' => $travelBundleGmvIdr,
                'total_gmv_idr' => $totalGmvIdr,
                'total_gmv_usd' => $totalGmvUsd,
            ];
        });
    }
}
