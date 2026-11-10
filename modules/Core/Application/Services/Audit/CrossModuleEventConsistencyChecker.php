<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Audit;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Models\CoreConsumerInbox;
use Modules\Core\Domain\Models\CoreEventDelivery;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Pemeriksa Konsistensi Aliran Event Lintas Modul (CrossModuleEventConsistencyChecker)
 *
 * Merekonsiliasi data antara:
 * - Event yang diterbitkan di tabel outbox / `sim_event_spine`
 * - Jejak penerimaan di `core_consumer_inbox` per grup konsumen
 * - Jejak pengiriman di `core_event_deliveries`
 *
 * Menghitung tingkat kehilangan (loss rate), keterlambatan (lag),
 * dan mendeteksi orphaned messages (event tanpa penerima).
 */
class CrossModuleEventConsistencyChecker
{
    /**
     * Menjalankan audit konsistensi lintas modul secara menyeluruh.
     *
     * @param  array<int, string>  $targetTopics
     * @return array{
     *     timestamp: string,
     *     total_spine_events: int,
     *     total_inbox_records: int,
     *     total_deliveries: int,
     *     reconciliation_rate_percent: float,
     *     orphan_event_count: int,
     *     topic_breakdown: array<string, array{
     *         published: int,
     *         delivered: int,
     *         dead_lettered: int,
     *         pending: int,
     *         loss_rate_percent: float
     *     }>,
     *     is_consistent: bool,
     *     anomalies: array<int, string>
     * }
     */
    public function audit(array $targetTopics = []): array
    {
        $spineQuery = SimEventSpine::query();
        if (! empty($targetTopics)) {
            $spineQuery->whereIn('topic', $targetTopics);
        }

        $totalSpine = $spineQuery->count();
        $totalInbox = CoreConsumerInbox::query()->count();
        $totalDeliveries = CoreEventDelivery::query()->count();

        // 1. Dapatkan agregasi per topik
        $topicStats = SimEventSpine::query()
            ->when(! empty($targetTopics), fn ($q) => $q->whereIn('topic', $targetTopics))
            ->select('topic', DB::raw('count(*) as count'))
            ->groupBy('topic')
            ->pluck('count', 'topic')
            ->toArray();

        $deliveryStats = DB::table('core_event_deliveries')
            ->join('sim_event_spine', 'core_event_deliveries.event_id', '=', 'sim_event_spine.id')
            ->when(! empty($targetTopics), fn ($q) => $q->whereIn('sim_event_spine.topic', $targetTopics))
            ->select(
                'sim_event_spine.topic',
                'core_event_deliveries.status',
                DB::raw('count(*) as count')
            )
            ->groupBy('sim_event_spine.topic', 'core_event_deliveries.status')
            ->get();

        $breakdown = [];
        $anomalies = [];

        foreach ($topicStats as $topic => $publishedCount) {
            $breakdown[$topic] = [
                'published' => (int) $publishedCount,
                'delivered' => 0,
                'dead_lettered' => 0,
                'pending' => 0,
                'loss_rate_percent' => 0.0,
            ];
        }

        foreach ($deliveryStats as $row) {
            $t = $row->topic;
            if (! isset($breakdown[$t])) {
                continue;
            }

            if ($row->status === 'delivered') {
                $breakdown[$t]['delivered'] += (int) $row->count;
            } elseif ($row->status === 'dead_lettered') {
                $breakdown[$t]['dead_lettered'] += (int) $row->count;
            } elseif ($row->status === 'pending' || $row->status === 'retrying') {
                $breakdown[$t]['pending'] += (int) $row->count;
            }
        }

        // 2. Hitung persentase kehilangan dan deteksi anomali
        $orphanCount = 0;
        foreach ($breakdown as $topic => &$stat) {
            $totalAccounted = $stat['delivered'] + $stat['dead_lettered'] + $stat['pending'];
            if ($stat['published'] > 0 && $totalAccounted === 0) {
                $orphanCount += $stat['published'];
                $anomalies[] = "Topik '{$topic}' memiliki {$stat['published']} event diterbitkan namun tidak memiliki konsumen yang berlangganan.";
            }

            if ($stat['dead_lettered'] > 0) {
                $lossRate = round(($stat['dead_lettered'] / max(1, $stat['published'])) * 100, 2);
                $stat['loss_rate_percent'] = $lossRate;

                if ($lossRate > 5.0) {
                    $anomalies[] = "Topik '{$topic}' memiliki tingkat DLQ tinggi: {$lossRate}% ({$stat['dead_lettered']} pesan gagal).";
                }
            }
        }
        unset($stat);

        // 3. Rekonsiliasi global
        $totalDelivered = CoreEventDelivery::query()->where('status', 'delivered')->count();
        $reconciliationRate = $totalSpine > 0
            ? round(($totalDelivered / max(1, $totalSpine)) * 100, 2)
            : 100.0;

        $isConsistent = empty($anomalies) && $orphanCount === 0;

        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_spine_events' => $totalSpine,
            'total_inbox_records' => $totalInbox,
            'total_deliveries' => $totalDeliveries,
            'reconciliation_rate_percent' => $reconciliationRate,
            'orphan_event_count' => $orphanCount,
            'topic_breakdown' => $breakdown,
            'is_consistent' => $isConsistent,
            'anomalies' => $anomalies,
        ];
    }
}
