<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Algorithms\Marketplace\SecondaryMarketTaxWaterfallEngine;
use Modules\Venue\Domain\Algorithms\Marketplace\SecondaryMarketTaxWaterfallInput;
use Modules\Venue\Domain\DTOs\SecondaryTaxReconciliationDTO;
use Modules\Venue\Domain\Models\TicketResale;
use Modules\Venue\Domain\StateMachines\TicketResaleStateMachine;

final class SecondaryMarketTaxReconciliationService
{
    public function __construct(
        private readonly SecondaryMarketTaxWaterfallEngine $waterfallEngine
    ) {}

    /**
     * Rekonsiliasi akuntansi tertutup pasar sekunder untuk satu periode bulan/tahun.
     */
    public function reconcilePeriod(int $year, int $month): SecondaryTaxReconciliationDTO
    {
        $resales = TicketResale::query()
            ->where('status', TicketResaleStateMachine::STATUS_SETTLED)
            ->whereYear('settled_at', $year)
            ->whereMonth('settled_at', $month)
            ->get();

        $grossTurnover = 0;
        $totalPbjt = 0;
        $totalPlatform = 0;
        $totalRoyalty = 0;
        $totalNetSeller = 0;

        foreach ($resales as $resale) {
            $input = new SecondaryMarketTaxWaterfallInput($resale->resale_price_idr, 1000, 800, 200);
            $calc = $this->waterfallEngine->calculate($input);

            $grossTurnover += $resale->resale_price_idr;
            $totalPbjt += $calc->pbjtTaxIdr;
            $totalPlatform += $calc->platformFeeIdr;
            $totalRoyalty += $calc->promoterRoyaltyIdr;
            $totalNetSeller += $calc->sellerPayoutIdr;
        }

        $sumComponents = $totalPbjt + $totalPlatform + $totalRoyalty + $totalNetSeller;
        $variance = $grossTurnover - $sumComponents;
        $isBalanced = ($variance === 0);

        return new SecondaryTaxReconciliationDTO(
            $year,
            $month,
            $resales->count(),
            $grossTurnover,
            $totalPbjt,
            $totalPlatform,
            $totalRoyalty,
            $totalNetSeller,
            $isBalanced,
            $variance,
            new \DateTimeImmutable
        );
    }
}
