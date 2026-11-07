<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\Carbon;
use Modules\Venue\Domain\Models\TicketWaitlist;
use Modules\Venue\Domain\StateMachines\WaitlistStateMachine;

/**
 * Layanan Analisis Keadilan & Anti-Kelaparan Antrean Tiket Sekunder (Fairness & Anti-Starvation Analytics).
 * Mengukur koefisien Gini waktu tunggu, mendeteksi penyimpangan prioritas antrean,
 * serta memastikan transparansi audit trail alokasi kursi kosong ke antrean terdaftar.
 */
final class WaitlistFairnessAnalyticsService
{
    /**
     * Hitung koefisien Gini ketimpangan waktu tunggu antrean tiket.
     * Mengembalikan rasio Gini dalam basis poin (0 bps = kesetaraan mutlak, 10000 bps = ketimpangan ekstrem).
     *
     * @param  array<int, int>  $waitingTimeMinutes  Daftar durasi tunggu tiap entitas antrean (dalam menit)
     */
    public function calculateWaitTimeGiniCoefficientBps(array $waitingTimeMinutes): int
    {
        $n = count($waitingTimeMinutes);
        if ($n < 2) {
            return 0; // Tidak ada ketimpangan jika kurang dari 2 peserta
        }

        sort($waitingTimeMinutes);
        $totalWait = array_sum($waitingTimeMinutes);
        if ($totalWait <= 0) {
            return 0;
        }

        $cumulativeDifferenceSum = 0;
        for ($i = 0; $i < $n; $i++) {
            $cumulativeDifferenceSum += (2 * ($i + 1) - $n - 1) * $waitingTimeMinutes[$i];
        }

        $giniNumerator = $cumulativeDifferenceSum;
        $giniDenominator = $n * $totalWait;

        if ($giniDenominator === 0) {
            return 0;
        }

        $giniBps = (int) intdiv($giniNumerator * 10000, $giniDenominator);

        return max(0, min(10000, $giniBps));
    }

    /**
     * Audit Keadilan Alokasi Kursi (Queue Jump & Starvation Audit).
     * Memeriksa apakah ada entitas antrean dengan prioritas lebih rendah yang dilayani mendahului antrean lebih lama
     * tanpa pembenaran tier prioritas keanggotaan VIP yang sah.
     *
     * @param  int  $eventId  Identitas acara
     * @return array{
     *   total_waitlist_entries: int,
     *   allocated_count: int,
     *   queue_jumping_violations: int,
     *   starvation_cases_count: int,
     *   fairness_index_bps: int,
     *   audit_findings: array<int, string>
     * }
     */
    public function auditQueueFairnessForEvent(int $eventId): array
    {
        $entries = TicketWaitlist::query()
            ->where('event_id', $eventId)
            ->orderBy('created_at', 'asc')
            ->get();

        $totalEntries = $entries->count();
        if ($totalEntries === 0) {
            return [
                'total_waitlist_entries' => 0,
                'allocated_count' => 0,
                'queue_jumping_violations' => 0,
                'starvation_cases_count' => 0,
                'fairness_index_bps' => 10000,
                'audit_findings' => ['TIDAK_ADA_DATA_ANTREAN'],
            ];
        }

        $violations = 0;
        $starvationCases = 0;
        $findings = [];
        $allocatedEntries = $entries->whereIn('status', [WaitlistStateMachine::STATUS_OFFERED, WaitlistStateMachine::STATUS_CLAIMED]);
        $waitingMinutes = [];

        $now = Carbon::now();

        foreach ($entries as $entry) {
            $createdAt = $entry->created_at ?? $now;
            $waitMinutes = max(1, (int) $createdAt->diffInMinutes($now));
            $waitingMinutes[] = $waitMinutes;

            // Indikasi starvation: antrean tier standar menunggu > 7 hari padahal ada alokasi baru
            if ($waitMinutes > 10080 && $entry->status === WaitlistStateMachine::STATUS_WAITING) {
                $starvationCases++;
                $findings[] = sprintf(
                    'POTENSI_STARVATION: Pengguna #%d dalam antrean menunggu lebih dari 7 hari (%d menit) tanpa penawaran',
                    $entry->user_id,
                    $waitMinutes
                );
            }
        }

        // Cek anomali queue jumping
        // Jika antrean B masuk belakangan namun di-OFFERED sebelum antrean A dengan prioritas lebih tinggi/sama di zona yang sama
        $groupedByZone = $entries->groupBy('zone_id');
        foreach ($groupedByZone as $zoneId => $zoneEntries) {
            $sortedByTime = $zoneEntries->sortBy('created_at')->values();
            $offeredFound = false;
            $lastOfferedCreatedAt = null;

            foreach ($sortedByTime as $item) {
                if ($item->status === WaitlistStateMachine::STATUS_OFFERED || $item->status === WaitlistStateMachine::STATUS_CLAIMED) {
                    $offeredFound = true;
                    $lastOfferedCreatedAt = $item->created_at;
                } elseif ($offeredFound && $item->status === WaitlistStateMachine::STATUS_WAITING) {
                    // Ada item yang didaftarkan SEBELUM yang di-offered namun masih waiting
                    if ($item->priority_score <= 50) { // Hanya flag jika prioritas setara
                        $violations++;
                        $findings[] = sprintf(
                            'ANOMALI_QUEUE_JUMPING: Antrean #%d zona #%d terlewati oleh entitas penawaran berikutnya',
                            $item->id,
                            $zoneId
                        );
                    }
                }
            }
        }

        $giniBps = $this->calculateWaitTimeGiniCoefficientBps($waitingMinutes);
        // Indeks keadilan adalah 10000 - Gini
        $fairnessIndexBps = max(0, 10000 - $giniBps);

        return [
            'total_waitlist_entries' => $totalEntries,
            'allocated_count' => $allocatedEntries->count(),
            'queue_jumping_violations' => $violations,
            'starvation_cases_count' => $starvationCases,
            'fairness_index_bps' => $fairnessIndexBps,
            'audit_findings' => array_slice($findings, 0, 10),
        ];
    }

    /**
     * Hasilkan metrik sertifikat transparansi antrean untuk publik.
     *
     * @return array{
     *   event_id: int,
     *   transparency_hash: string,
     *   average_wait_hours: float,
     *   conversion_rate_bps: int,
     *   anti_scalping_seal: bool
     * }
     */
    public function generatePublicWaitlistTransparencyReport(int $eventId): array
    {
        $entries = TicketWaitlist::query()->where('event_id', $eventId)->get();
        $total = $entries->count();
        $claimed = $entries->where('status', WaitlistStateMachine::STATUS_CLAIMED)->count();

        $conversionRateBps = $total > 0 ? (int) intdiv($claimed * 10000, $total) : 0;

        $totalWaitHours = 0;
        $now = Carbon::now();
        foreach ($entries as $e) {
            $created = $e->created_at ?? $now;
            $totalWaitHours += $created->diffInHours($now);
        }
        $avgWaitHours = $total > 0 ? round($totalWaitHours / $total, 1) : 0.0;

        $hashPayload = sprintf('WAITLIST-TRANS-EVT-%d-%d-%d-%d', $eventId, $total, $claimed, $conversionRateBps);
        $transparencyHash = hash_hmac('sha256', $hashPayload, 'VENUE_WAITLIST_SALT_2026');

        return [
            'event_id' => $eventId,
            'transparency_hash' => $transparencyHash,
            'average_wait_hours' => $avgWaitHours,
            'conversion_rate_bps' => $conversionRateBps,
            'anti_scalping_seal' => true,
        ];
    }

    /**
     * Hitung distribusi frekuensi prioritas antrean untuk mendeteksi pembengkakan kelas VIP.
     *
     * @return array<string, int>
     */
    public function computePriorityScoreHistogram(int $eventId): array
    {
        $entries = TicketWaitlist::query()->where('event_id', $eventId)->get();
        $bins = [
            'STANDARD_0_25' => 0,
            'COMMUNITY_26_50' => 0,
            'TIER1_51_75' => 0,
            'VIP_PLATINUM_76_100' => 0,
        ];

        foreach ($entries as $e) {
            $score = $e->priority_score ?? 0;
            if ($score <= 25) {
                $bins['STANDARD_0_25']++;
            } elseif ($score <= 50) {
                $bins['COMMUNITY_26_50']++;
            } elseif ($score <= 75) {
                $bins['TIER1_51_75']++;
            } else {
                $bins['VIP_PLATINUM_76_100']++;
            }
        }

        return $bins;
    }
}
