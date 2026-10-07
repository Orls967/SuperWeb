<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Modules\Mining\Domain\Models\RoyaltyCalculation;
use Modules\Mining\Domain\Models\WeighbridgeTicket;

class MiningComplianceAndRoyaltyService
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Issue hash-chained weighbridge ticket
     */
    public function recordWeighbridgeTicket(
        string $siteId,
        string $truckPlateNumber,
        float $grossWeightTon,
        float $tareWeightTon,
        float $nickelGradePercentage,
        string $destinationStockpile
    ): WeighbridgeTicket {
        $netWeightTon = max(0.00, $grossWeightTon - $tareWeightTon);

        $lastTicket = WeighbridgeTicket::where('site_id', $siteId)->latest('created_at')->first();
        $prevHash = $lastTicket?->hash ?? 'GENESIS_MINING_HASH';

        $ticketNumber = 'WB-'.strtoupper(Str::random(8));
        $hash = hash('sha256', $ticketNumber.$truckPlateNumber.$netWeightTon.$prevHash);

        return WeighbridgeTicket::create([
            'id' => (string) Str::uuid(),
            'site_id' => $siteId,
            'ticket_number' => $ticketNumber,
            'truck_plate_number' => $truckPlateNumber,
            'gross_weight_ton' => $grossWeightTon,
            'tare_weight_ton' => $tareWeightTon,
            'net_weight_ton' => $netWeightTon,
            'nickel_grade_percentage' => $nickelGradePercentage,
            'destination_stockpile' => $destinationStockpile,
            'hash' => $hash,
            'previous_hash' => $prevHash,
        ]);
    }

    /**
     * Calculate PNBP mining royalty and post liability to Ledger
     * royalty = total_ton * benchmark_price * (royalty_rate% / 100)
     */
    public function calculateAndPostRoyalty(
        string $siteId,
        string $period,
        float $totalProductionTon,
        int $benchmarkPriceIdr,
        float $royaltyRatePercentage
    ): RoyaltyCalculation {
        $site = MiningSite::findOrFail($siteId);

        $totalSalesValue = (float) $totalProductionTon * $benchmarkPriceIdr;
        $royaltyDue = (int) round(($totalSalesValue * $royaltyRatePercentage) / 100);

        $royalty = RoyaltyCalculation::updateOrCreate(
            ['site_id' => $siteId, 'period' => $period],
            [
                'id' => (string) Str::uuid(),
                'total_production_ton' => $totalProductionTon,
                'commodity_benchmark_price_idr' => $benchmarkPriceIdr,
                'royalty_rate_percentage' => $royaltyRatePercentage,
                'royalty_due_idr' => $royaltyDue,
                'status' => 'accrued',
            ]
        );

        if ($this->ledger && $royaltyDue > 0) {
            $this->ledger->post(new PostingDTO(
                type: 'MINING_ROYALTY_ACCRUAL',
                description: "PNBP mining royalty accrual for site {$site->name} ({$period})",
                idempotencyKey: "min_royalty:{$siteId}:{$period}",
                entries: [
                    PostingEntryDTO::forCode('expense:mining_royalty:IDR', 'IDR', -$royaltyDue),
                    PostingEntryDTO::forCode('min:royalty_payable:IDR', 'IDR', $royaltyDue),
                ],
                referenceType: 'min_royalty_calculations',
                referenceId: $royalty->id,
            ));
        }

        return $royalty;
    }

    /**
     * Verify HSE work permit validity
     */
    public function verifyWorkPermit(string $permitId): bool
    {
        $permit = MiningWorkPermit::findOrFail($permitId);

        if ($permit->status !== 'active') {
            return false;
        }

        if (now()->greaterThan($permit->valid_until)) {
            $permit->update(['status' => 'expired']);

            return false;
        }

        return true;
    }
}
