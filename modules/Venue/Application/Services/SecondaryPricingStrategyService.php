<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\TicketResale;
use Modules\Venue\Domain\StateMachines\TicketResaleStateMachine;
use Modules\Venue\Domain\ValueObjects\ResalePriceCap;

final class SecondaryPricingStrategyService
{
    /**
     * Rekomendasikan rentang harga jual sekunder yang optimal berdasarkan face value dan sisa hari ke konser.
     *
     * @return array{
     *   face_value_idr: int,
     *   min_recommended_price_idr: int,
     *   max_allowed_price_idr: int,
     *   recommended_price_idr: int,
     *   pricing_strategy: string,
     *   market_demand_level: string
     * }
     */
    public function getOptimalPricingRecommendation(EntertainmentTicket $ticket): array
    {
        $faceValue = $ticket->price_idr;
        $priceCap = new ResalePriceCap($faceValue);
        $maxAllowed = $priceCap->maxAllowedPriceIdr;

        $event = $ticket->event;
        $daysUntilEvent = 30; // bawaan bila event tidak ditemukan

        if ($event !== null && $event->event_date !== null) {
            $eventDate = new \DateTimeImmutable((string) $event->event_date);
            $now = new \DateTimeImmutable('today');
            $diff = $now->diff($eventDate);
            $daysUntilEvent = (int) $diff->format('%r%a');
        }

        // Hitung kepadatan pasar sekunder saat ini
        $activeListings = TicketResale::query()
            ->where('ticket_id', $ticket->id)
            ->where('status', TicketResaleStateMachine::STATUS_LISTED)
            ->count();

        if ($daysUntilEvent <= 2) {
            // Menjelang hari H: likuidasi cepat disarankan pada 95%-105% face value
            $strategy = 'URGENT_LIQUIDATION';
            $demand = 'HIGH_URGENCY';
            $recommended = (int) ($faceValue * 1.00);
            $minRec = (int) ($faceValue * 0.85);
        } elseif ($daysUntilEvent <= 14) {
            // 2 minggu sebelum event: permintaan puncak, disarankan 110%-118% face value
            $strategy = 'PEAK_DEMAND_HARVEST';
            $demand = 'PEAK_HIGH';
            $recommended = (int) ($faceValue * 1.15);
            $minRec = (int) ($faceValue * 0.95);
        } else {
            // Waktu masih longgar: stabil di face value s/d 110%
            $strategy = 'EARLY_STABLE_MARKET';
            $demand = 'MODERATE';
            $recommended = (int) ($faceValue * 1.05);
            $minRec = (int) ($faceValue * 0.90);
        }

        // Batasi dengan hard legal cap
        $recommended = min($recommended, $maxAllowed);

        return [
            'face_value_idr' => $faceValue,
            'min_recommended_price_idr' => $minRec,
            'max_allowed_price_idr' => $maxAllowed,
            'recommended_price_idr' => $recommended,
            'pricing_strategy' => $strategy,
            'market_demand_level' => $demand,
        ];
    }
}
