<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Waitlist;

class WaitlistPriorityGovernor
{
    private const TIER_SCORES = [
        'INFINITY' => 100000,
        'MOON' => 50000,
        'SUN' => 25000,
        'REGULER' => 0,
    ];

    /**
     * Menghitung skor prioritas antrean tiket berdasarkan tier loyalty member,
     * total pengeluaran 365 hari, dan lama pendaftaran dalam antrean.
     */
    public function rankCandidates(WaitlistGovernorInput $input): WaitlistGovernorResult
    {
        $scored = [];

        foreach ($input->candidates as $candidate) {
            $userId = (int) $candidate['user_id'];
            $tier = strtoupper((string) ($candidate['tier'] ?? 'REGULER'));
            $spend = (int) ($candidate['spend_365_idr'] ?? 0);
            $secondsAgo = (int) ($candidate['registered_seconds_ago'] ?? 0);

            $tierScore = self::TIER_SCORES[$tier] ?? 0;
            // 1 poin tiap Rp 1.000 belanja tahunan
            $spendScore = intdiv($spend, 1000);
            // 1 poin tiap 10 detik antrean (senioritas pendaftaran)
            $seniorityScore = intdiv($secondsAgo, 10);

            $totalScore = $tierScore + $spendScore + $seniorityScore;

            $scored[] = [
                'user_id' => $userId,
                'score' => $totalScore,
            ];
        }

        // Urutkan menurun berdasarkan total score
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $rankedUserIds = array_column($scored, 'user_id');
        $rankedScores = array_column($scored, 'score');

        $signature = WaitlistPriorityVerifier::sign($rankedUserIds, $rankedScores, count($scored));

        $cert = new WaitlistPriorityCertificate(
            rankedUserIds: $rankedUserIds,
            rankedScores: $rankedScores,
            candidateCount: count($scored),
            hmacSignature: $signature
        );

        return new WaitlistGovernorResult(
            rankedUserIds: $rankedUserIds,
            rankedScores: $rankedScores,
            certificate: $cert
        );
    }
}
