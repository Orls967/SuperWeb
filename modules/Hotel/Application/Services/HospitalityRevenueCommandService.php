<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\RevenueCommandMetric;
use Modules\Hotel\Domain\Models\SyndicationInvestor;

class HospitalityRevenueCommandService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 118.1 & 118.5 (a) Compute Cross-Network Revenue Command Metric: RevPAR = ADR * Occupancy
     */
    public function computeRegionalRevenueMetric(
        string $regionCity,
        int $year,
        int $month,
        int $adrIdr,
        float $occupancyPercent,
        int $venueTicketGmvIdr,
        int $travelBundleGmvIdr
    ): RevenueCommandMetric {
        $revparIdr = (int) round(($adrIdr * $occupancyPercent) / 100);

        return RevenueCommandMetric::create([
            'id' => (string) Str::uuid(),
            'metric_code' => "RCM-{$regionCity}-{$year}".sprintf('%02d', $month),
            'region_city' => $regionCity,
            'reporting_year' => $year,
            'reporting_month' => $month,
            'adr_idr' => $adrIdr,
            'occupancy_percentage' => $occupancyPercent,
            'revpar_idr' => $revparIdr,
            'total_venue_ticket_gmv_idr' => $venueTicketGmvIdr,
            'total_travel_bundle_gmv_idr' => $travelBundleGmvIdr,
        ]);
    }

    /**
     * 118.3 & 118.5 (c) Syndication JV Property: Distribute Net Rental Yield to Token Holders
     */
    public function distributeSyndicationYield(
        HotelProperty $property,
        int $investorUserId,
        int $tokensHeld,
        float $ownershipPercent,
        int $netMonthlyPropertyYieldIdr
    ): SyndicationInvestor {
        $investorShareIdr = (int) round(($netMonthlyPropertyYieldIdr * $ownershipPercent) / 100);

        return DB::transaction(function () use (
            $property,
            $investorUserId,
            $tokensHeld,
            $ownershipPercent,
            $investorShareIdr
        ) {
            $syndication = SyndicationInvestor::create([
                'id' => (string) Str::uuid(),
                'syndication_code' => 'SYN-'.strtoupper(bin2hex(random_bytes(4))),
                'property_id' => $property->id,
                'investor_user_id' => $investorUserId,
                'fractional_tokens_held' => $tokensHeld,
                'ownership_percentage' => $ownershipPercent,
                'distributed_yield_idr' => $investorShareIdr,
            ]);

            // Post syndication rental yield in ledger
            $this->ledgerService->post(new PostingDTO(
                type: 'HOTEL_SYNDICATION_YIELD_DISTRIBUTION',
                description: "Syndication token rental yield for property {$property->property_code} to investor {$investorUserId}",
                idempotencyKey: "HTL-SYN-{$syndication->id}",
                entries: [
                    PostingEntryDTO::forCode('htl:syndication_yield_clearing:IDR', 'IDR', $investorShareIdr),
                    PostingEntryDTO::forCode('htl:investor_token_payable:IDR', 'IDR', -$investorShareIdr),
                ],
                referenceType: 'SYNDICATION_INVESTOR',
                referenceId: $syndication->id,
            ));

            return $syndication;
        });
    }
}
