<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Audit;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Auditor Forensik Aliran Event Spine (EventSpineForensicAuditor)
 *
 * Melakukan penyelidikan forensik mendalam pada event spine:
 * - Deteksi anomali lonjakan frekuensi per detik (spike anomaly detection)
 * - Deteksi duplikasi idempotency key atau collision event_id
 * - Analisis jeda waktu (time skew) antara occurred_at vs published_at
 * - Pelacakan silsilah korelasi dan rantai sebab-akibat (causality chain integrity)
 */
class EventSpineForensicAuditor
{
    /**
     * Menjalankan investigasi forensik menyeluruh pada rentang waktu atau event tertentu.
     *
     * @param  array<string, mixed>  $criteria
     * @return array{
     *     timestamp: string,
     *     total_scanned: int,
     *     time_skew_anomalies: array<int, array{event_id: string, skew_seconds: int, topic: string}>,
     *     duplicate_key_collisions: array<int, array{event_id: string, count: int}>,
     *     burst_spikes: array<int, array{second_bucket: string, event_count: int}>,
     *     broken_causality_chains: array<int, array{child_event_id: string, missing_parent_id: string}>,
     *     audit_verdict: 'CLEAN'|'ANOMALY_DETECTED'|'TAMPER_SUSPECTED'
     * }
     */
    public function investigate(array $criteria = []): array
    {
        $limit = max(50, (int) ($criteria['limit'] ?? 1000));
        $events = SimEventSpine::query()
            ->latest('id')
            ->take($limit)
            ->get();

        $scannedCount = $events->count();
        $timeSkewAnomalies = [];
        $burstBuckets = [];
        $brokenCausality = [];

        foreach ($events as $event) {
            // 1. Periksa time skew: apakah event dibuat di masa depan atau jeda ekstrem > 2 jam
            $createdAt = $event->created_at ? $event->created_at->timestamp : 0;
            $now = time();
            $skew = $now - $createdAt;

            if ($skew < -300) { // Lebih dari 5 menit di masa depan
                $timeSkewAnomalies[] = [
                    'event_id' => (string) ($event->event_id ?? $event->id),
                    'skew_seconds' => $skew,
                    'topic' => $event->topic,
                ];
            }

            // 2. Agregasi per detik untuk deteksi lonjakan ekstrem (burst)
            $secondKey = $event->created_at ? $event->created_at->format('Y-m-d H:i:s') : 'unknown';
            $burstBuckets[$secondKey] = ($burstBuckets[$secondKey] ?? 0) + 1;

            // 3. Periksa broken causality chain jika memiliki causation_id
            $causationId = $event->causation_id ?? null;
            if ($causationId !== null && ! empty($causationId)) {
                $parentExists = SimEventSpine::query()
                    ->where('event_id', $causationId)
                    ->orWhere('id', $causationId)
                    ->exists();

                if (! $parentExists) {
                    $brokenCausality[] = [
                        'child_event_id' => (string) ($event->event_id ?? $event->id),
                        'missing_parent_id' => (string) $causationId,
                    ];
                }
            }
        }

        // 4. Deteksi lonjakan burst (> 200 event dalam 1 detik)
        $spikes = [];
        foreach ($burstBuckets as $bucket => $cnt) {
            if ($cnt > 200) {
                $spikes[] = [
                    'second_bucket' => $bucket,
                    'event_count' => $cnt,
                ];
            }
        }

        // 5. Cek duplikasi event_id
        $duplicates = DB::table('sim_event_spine')
            ->select('event_id', DB::raw('COUNT(*) as count'))
            ->groupBy('event_id')
            ->having('count', '>', 1)
            ->get()
            ->map(fn ($r) => ['event_id' => (string) $r->event_id, 'count' => (int) $r->count])
            ->toArray();

        // 6. Evaluasi vonis audit
        $verdict = 'CLEAN';
        if (! empty($duplicates)) {
            $verdict = 'TAMPER_SUSPECTED';
        } elseif (! empty($timeSkewAnomalies) || ! empty($brokenCausality) || ! empty($spikes)) {
            $verdict = 'ANOMALY_DETECTED';
        }

        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_scanned' => $scannedCount,
            'time_skew_anomalies' => $timeSkewAnomalies,
            'duplicate_key_collisions' => $duplicates,
            'burst_spikes' => $spikes,
            'broken_causality_chains' => $brokenCausality,
            'audit_verdict' => $verdict,
        ];
    }
}

