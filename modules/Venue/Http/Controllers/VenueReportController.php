<?php

declare(strict_types=1);

namespace Modules\Venue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\GateScanLog;
use Modules\Venue\Domain\Models\TicketTier;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VenueReportController extends Controller
{
    public function index(Request $request): View
    {
        $tierSales = TicketTier::with('event')
            ->get()
            ->map(function (TicketTier $tier) {
                $ticketsCount = EntertainmentTicket::where('tier_id', $tier->id)->count();
                $totalRevenue = EntertainmentTicket::where('tier_id', $tier->id)->sum('price_paid_idr');

                return [
                    'tier_name' => $tier->name,
                    'event_title' => $tier->event?->title ?? 'N/A',
                    'base_price' => $tier->base_price_idr,
                    'allocated_capacity' => $tier->allocated_capacity,
                    'tickets_sold' => $ticketsCount,
                    'total_revenue_idr' => $totalRevenue,
                ];
            });

        $totalIssued = EntertainmentTicket::count();
        $totalScanned = GateScanLog::where('scan_result', 'ALLOWED')->count();
        $totalDenied = GateScanLog::where('scan_result', 'DENIED')->count();
        $grossTicketRevenue = EntertainmentTicket::sum('price_paid_idr');

        return view('venue::reports.index', [
            'tierSales' => $tierSales,
            'totalIssued' => $totalIssued,
            'totalScanned' => $totalScanned,
            'totalDenied' => $totalDenied,
            'grossTicketRevenue' => $grossTicketRevenue,
        ]);
    }

    public function pnl(): View
    {
        return view('venue::reports.pnl');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="venue_ticket_sales_report_' . date('Y-m-d_His') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Ticket ID',
                'Ticket Number',
                'Event Code',
                'Event Title',
                'Buyer User ID',
                'Price Paid (IDR)',
                'Status',
                'Reentry Count',
                'Ticket Hash',
                'Issued At',
            ]);

            EntertainmentTicket::with('event')
                ->chunk(200, function ($tickets) use ($handle) {
                    foreach ($tickets as $ticket) {
                        fputcsv($handle, [
                            $ticket->id,
                            $ticket->ticket_number,
                            $ticket->event?->event_code ?? '',
                            $ticket->event?->title ?? '',
                            $ticket->buyer_user_id,
                            $ticket->price_paid_idr,
                            $ticket->status,
                            $ticket->reentry_count,
                            $ticket->ticket_hash,
                            $ticket->created_at ? $ticket->created_at->toIso8601String() : '',
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }
}

