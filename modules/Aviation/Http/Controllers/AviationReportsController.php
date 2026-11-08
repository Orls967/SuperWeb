<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationRefund;
use Modules\Aviation\Domain\Models\AviationTicket;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AviationReportsController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_flights' => AviationFlight::count(),
            'total_tickets' => AviationTicket::count(),
            'total_refunds' => AviationRefund::count(),
        ];

        return view('aviation::reports.index', [
            'stats' => $stats,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $type = $request->query('type', 'flights');
        $filename = "aviation-{$type}-".date('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($type) {
            $handle = fopen('php://output', 'w');

            if ($type === 'tickets') {
                fputcsv($handle, ['Ticket Number', 'Flight', 'Cabin', 'Status', 'Base Fare (IDR)', 'Tax (IDR)', 'Total (IDR)', 'Created At']);
                AviationTicket::with('flight')->chunk(200, function ($tickets) use ($handle) {
                    foreach ($tickets as $t) {
                        fputcsv($handle, [
                            $t->ticket_number,
                            $t->flight ? $t->flight->flight_number : '-',
                            $t->cabin,
                            $t->status,
                            number_format($t->base_fare_minor / 100, 2, '.', ''),
                            number_format($t->tax_minor / 100, 2, '.', ''),
                            number_format($t->total_fare_minor / 100, 2, '.', ''),
                            $t->created_at->toIso8601String(),
                        ]);
                    }
                });
            } elseif ($type === 'refunds') {
                fputcsv($handle, ['Refund ID', 'Ticket ID', 'Original Fare', 'Admin Fee', 'Tax Refund', 'Net Refund', 'Reason', 'Created At']);
                AviationRefund::chunk(200, function ($refunds) use ($handle) {
                    foreach ($refunds as $r) {
                        fputcsv($handle, [
                            $r->id,
                            $r->ticket_id,
                            $r->original_fare_minor,
                            $r->admin_fee_minor,
                            $r->tax_refund_minor,
                            $r->net_refund_minor,
                            $r->reason,
                            $r->created_at->toIso8601String(),
                        ]);
                    }
                });
            } else {
                fputcsv($handle, ['Flight Number', 'Operator', 'Origin', 'Destination', 'Departure', 'Arrival', 'Status', 'Fuel Burn (kg)']);
                AviationFlight::with(['operator', 'origin', 'destination'])->chunk(200, function ($flights) use ($handle) {
                    foreach ($flights as $f) {
                        fputcsv($handle, [
                            $f->flight_number,
                            $f->operator ? $f->operator->code : '-',
                            $f->origin ? $f->origin->iata_code : '-',
                            $f->destination ? $f->destination->iata_code : '-',
                            $f->departure_time->toIso8601String(),
                            $f->arrival_time->toIso8601String(),
                            $f->status,
                            $f->fuel_burn_kg,
                        ]);
                    }
                });
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}

