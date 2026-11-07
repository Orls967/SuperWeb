<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\GateScanLog;

class VenuePartitioningService
{
    /**
     * Menganalisis skala data T2 dan memastikan strategi partisi per event/bulan optimal.
     *
     * @return array{
     *     total_tickets: int,
     *     total_scans: int,
     *     active_events: int,
     *     recommended_partition_strategy: string,
     *     estimated_indexes_size_mb: float,
     *     query_budget_per_minute: int
     * }
     */
    public function analyzePartitionScale(): array
    {
        $ticketCount = EntertainmentTicket::count();
        $scanCount = GateScanLog::count();
        $eventCount = EntertainmentEvent::where('status', '!=', 'cancelled')->count();

        // Estimasi ukuran indeks SQLite / MySQL untuk skala T2 (120k tiket + 400k scan)
        $estimatedMb = round(($ticketCount * 0.0005) + ($scanCount * 0.0004), 2);

        $strategy = match (true) {
            $ticketCount >= 100_000 => 'RANGE_PARTITION_BY_EVENT_ID_HASH_SUBPARTITION',
            $ticketCount >= 20_000 => 'LIST_PARTITION_BY_EVENT_STATUS',
            default => 'STANDARD_BTREE_COMPOSITE_INDEX',
        };

        return [
            'total_tickets' => $ticketCount,
            'total_scans' => $scanCount,
            'active_events' => $eventCount,
            'recommended_partition_strategy' => $strategy,
            'estimated_indexes_size_mb' => max(0.5, $estimatedMb),
            'query_budget_per_minute' => 600, // Budget 600 query/menit tanpa N+1
        ];
    }

    /**
     * Mengelompokkan pindaian tiket per jendela waktu menit untuk mencegah table lock kontensi tinggi.
     *
     * @return array<string, int>
     */
    public function aggregateScansPerMinute(int $eventId): array
    {
        return DB::table('ven_gate_scan_logs')
            ->join('ven_entertainment_tickets', 'ven_gate_scan_logs.ticket_id', '=', 'ven_entertainment_tickets.id')
            ->where('ven_entertainment_tickets.event_id', $eventId)
            ->selectRaw("strftime('%Y-%m-%d %H:%M', ven_gate_scan_logs.scanned_at) as scan_minute, count(*) as scan_count")
            ->groupBy('scan_minute')
            ->orderBy('scan_minute')
            ->pluck('scan_count', 'scan_minute')
            ->toArray();
    }
}
