<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\ViewModels;

use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationRefund;
use Modules\Aviation\Domain\Models\AviationTicket;

class AviationReportViewModel
{
    /**
     * Membangun data laporan kinerja komersial & keuangan aviasi per rentang waktu.
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function build(string $startDate, string $endDate): array
    {
        $flights = AviationFlight::with(['originAirport', 'destinationAirport'])
            ->whereBetween('departure_time', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->get();

        $tickets = AviationTicket::whereIn('flight_id', $flights->pluck('id'))->get();
        $refunds = AviationRefund::whereIn('ticket_id', $tickets->pluck('id'))->get();

        $totalRevenueEarnedMinor = $tickets->where('status', 'flown')->sum('base_fare_minor');
        $totalUnearnedMinor = $tickets->whereIn('status', ['issued', 'checked_in', 'boarded'])->sum('base_fare_minor');
        $totalRefundMinor = $refunds->sum('net_refund_minor');
        $totalTaxMinor = $tickets->sum('tax_minor');

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'flights_summary' => [
                'total_scheduled' => $flights->count(),
                'departed' => $flights->where('status', 'departed')->count(),
                'arrived' => $flights->where('status', 'arrived')->count(),
                'cancelled' => $flights->where('status', 'cancelled')->count(),
            ],
            'passenger_summary' => [
                'total_tickets' => $tickets->count(),
                'flown' => $tickets->where('status', 'flown')->count(),
                'refunded' => $tickets->where('status', 'refunded')->count(),
                'no_show' => $tickets->where('status', 'no_show')->count(),
            ],
            'financial_summary' => [
                'earned_revenue_idr' => round($totalRevenueEarnedMinor / 100, 2),
                'unearned_revenue_idr' => round($totalUnearnedMinor / 100, 2),
                'refund_settled_idr' => round($totalRefundMinor / 100, 2),
                'tax_collected_idr' => round($totalTaxMinor / 100, 2),
            ],
        ];
    }
}

