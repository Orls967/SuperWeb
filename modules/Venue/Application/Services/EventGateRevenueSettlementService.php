<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;

final class EventGateRevenueSettlementService
{
    public const PBJT_TAX_BPS = 1000;         // 10% Pajak Hiburan
    public const PLATFORM_TICKETING_FEE_BPS = 500; // 5% Biaya Platform
    public const INSURANCE_PREMIUM_BPS = 200;      // 2% Asuransi Event

    /**
     * Compute comprehensive event gate financial waterfall.
     *
     * @return array{
     *     event_code: string,
     *     total_tickets_sold: int,
     *     gross_ticket_revenue_idr: int,
     *     pbjt_entertainment_tax_idr: int,
     *     platform_service_fee_idr: int,
     *     event_insurance_premium_idr: int,
     *     net_promoter_settlement_idr: int,
     *     average_ticket_yield_idr: int
     * }
     */
    public function computeEventGateSettlement(EntertainmentEvent $event): array
    {
        $validTickets = EntertainmentTicket::where('event_id', $event->id)
            ->whereIn('status', ['issued', 'scanned_in', 'scanned_out', 'used'])
            ->get();

        $totalTickets = $validTickets->count();
        $grossRevenue = (int) $validTickets->sum('price_paid_idr');

        $pbjtTax = (int) floor(($grossRevenue * self::PBJT_TAX_BPS) / 10000);
        $platformFee = (int) floor(($grossRevenue * self::PLATFORM_TICKETING_FEE_BPS) / 10000);
        $insurance = (int) floor(($grossRevenue * self::INSURANCE_PREMIUM_BPS) / 10000);

        $netPromoter = max(0, $grossRevenue - ($pbjtTax + $platformFee + $insurance));
        $avgYield = $totalTickets > 0 ? (int) floor($grossRevenue / $totalTickets) : 0;

        return [
            'event_code' => $event->event_code,
            'total_tickets_sold' => $totalTickets,
            'gross_ticket_revenue_idr' => $grossRevenue,
            'pbjt_entertainment_tax_idr' => $pbjtTax,
            'platform_service_fee_idr' => $platformFee,
            'event_insurance_premium_idr' => $insurance,
            'net_promoter_settlement_idr' => $netPromoter,
            'average_ticket_yield_idr' => $avgYield,
        ];
    }
}
