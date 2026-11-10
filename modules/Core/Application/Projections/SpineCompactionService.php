<?php

declare(strict_types=1);

namespace Modules\Core\Application\Projections;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Layanan Pemadatan Riwayat Aliran Stream (SpineCompactionService)
 *
 * Menerapkan prinsip Log Compaction pada event spine:
 * Mempertahankan hanya state event terakhir untuk setiap aggregateId yang telah berstatus selesai (terminal state),
 * sehingga mempercepat waktu bootstrap replay dan mengurangi konsumsi ruang disk tanpa kehilangan konsistensi audit.
 */
class SpineCompactionService
{
    /**
     * Menjalankan pemadatan stream untuk topik atau aggregate tertentu.
     *
     * @param  array<string, mixed>  $options
     * @return array{
     *     topic: string|null,
     *     scanned_events: int,
     *     compacted_aggregates: int,
     *     pruned_events: int,
     *     execution_time_ms: float
     * }
     */
    public function compactStream(?string $topic = null, array $options = []): array
    {
        $startTime = microtime(true);
        $retentionDays = max(7, (int) ($options['retention_days'] ?? 30));
        $cutoffDate = now()->subDays($retentionDays);

        $query = SimEventSpine::query()
            ->where('created_at', '<', $cutoffDate);

        if ($topic !== null) {
            $query->where('topic', $topic);
        }

        $scannedCount = $query->count();

        // Cari ID tertinggi per aggregate_type dan aggregate_id
        $latestPerAggregate = DB::table('sim_event_spine')
            ->where('created_at', '<', $cutoffDate)
            ->when($topic !== null, fn ($q) => $q->where('topic', $topic))
            ->whereNotNull('aggregate_id')
            ->select('aggregate_type', 'aggregate_id', DB::raw('MAX(id) as max_id'), DB::raw('COUNT(*) as total_records'))
            ->groupBy('aggregate_type', 'aggregate_id')
            ->having('total_records', '>', 1)
            ->get();

        $prunedCount = 0;
        $compactedAggregates = 0;

        foreach ($latestPerAggregate as $record) {
            // Hapus event-event lama dari aggregate tersebut kecuali event terakhir (max_id)
            $deleted = DB::table('sim_event_spine')
                ->where('aggregate_type', $record->aggregate_type)
                ->where('aggregate_id', $record->aggregate_id)
                ->where('id', '<', $record->max_id)
                ->delete();

            if ($deleted > 0) {
                $prunedCount += $deleted;
                $compactedAggregates++;
            }
        }

        $durationMs = (microtime(true) - $startTime) * 1000.0;

        return [
            'topic' => $topic,
            'scanned_events' => $scannedCount,
            'compacted_aggregates' => $compactedAggregates,
            'pruned_events' => $prunedCount,
            'execution_time_ms' => round($durationMs, 2),
        ];
    }

    /**
     * Mengambil ringkasan potensi penghematan ruang jika dilakukan pemadatan log.
     *
     * @return array{estimated_prunable_events: int, aggregate_candidates: int}
     */
    public function estimateSavings(int $retentionDays = 30): array
    {
        $cutoffDate = now()->subDays($retentionDays);

        $results = DB::table('sim_event_spine')
            ->where('created_at', '<', $cutoffDate)
            ->whereNotNull('aggregate_id')
            ->select('aggregate_type', 'aggregate_id', DB::raw('COUNT(*) as total_records'))
            ->groupBy('aggregate_type', 'aggregate_id')
            ->having('total_records', '>', 1)
            ->get();

        $prunable = 0;
        foreach ($results as $row) {
            $prunable += ($row->total_records - 1);
        }

        return [
            'estimated_prunable_events' => $prunable,
            'aggregate_candidates' => $results->count(),
        ];
    }
}

