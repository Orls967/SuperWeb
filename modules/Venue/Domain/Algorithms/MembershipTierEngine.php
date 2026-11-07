<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

use InvalidArgumentException;

/**
 * Algoritma V7: MembershipTierEngine
 *
 * Menentukan tingkat keanggotaan tahunan beach club (Sun, Moon, Infinity)
 * berdasarkan akumulasi belanja bergulir 365 hari dan frekuensi kunjungan terverifikasi di gerbang turnstile,
 * perlindungan grace period penurunan tingkat, alokasi bottle storage locker, dan kalkulasi poin loyalitas.
 */
final class MembershipTierEngine
{
    public const int THRESHOLD_INFINITY_SPEND = 75_000_000;

    public const int THRESHOLD_INFINITY_VISITS = 24;

    public const int THRESHOLD_MOON_SPEND = 20_000_000;

    public const int THRESHOLD_MOON_VISITS = 12;

    public function evaluate(MembershipTierInput $input): MembershipTierResult
    {
        if ($input->spend365DaysIdr < 0 || $input->visits365Days < 0) {
            throw new InvalidArgumentException('Belanja dan kunjungan tidak boleh negatif.');
        }

        // 1. Evaluasi Kualifikasi Natural
        $naturalTier = 'sun';
        if ($input->spend365DaysIdr >= self::THRESHOLD_INFINITY_SPEND && $input->visits365Days >= self::THRESHOLD_INFINITY_VISITS) {
            $naturalTier = 'infinity';
        } elseif ($input->spend365DaysIdr >= self::THRESHOLD_MOON_SPEND || $input->visits365Days >= self::THRESHOLD_MOON_VISITS) {
            $naturalTier = 'moon';
        }

        $resolvedTier = $naturalTier;
        $inGracePeriod = false;

        // 2. Logika Proteksi Penurunan Tingkat (Downgrade Grace Period)
        $currentTier = strtolower($input->currentTier);
        $tierRanks = ['sun' => 1, 'moon' => 2, 'infinity' => 3];
        $currentRank = $tierRanks[$currentTier] ?? 1;
        $naturalRank = $tierRanks[$naturalTier] ?? 1;

        if ($naturalRank < $currentRank) {
            // Jika bukan evaluasi ulang ulang tahun tahunan, tier dipertahankan
            if (! $input->isAnniversaryEvaluation) {
                $resolvedTier = $currentTier;
            } else {
                // Saat anniversary evaluation, periksa pemenuhan 80% ambang batas untuk grace period 30 hari
                $thresholdSpend = match ($currentTier) {
                    'infinity' => self::THRESHOLD_INFINITY_SPEND,
                    'moon' => self::THRESHOLD_MOON_SPEND,
                    default => 0,
                };

                $eightyPercentThreshold = (int) intdiv($thresholdSpend * 8, 10);
                if ($input->spend365DaysIdr >= $eightyPercentThreshold) {
                    $inGracePeriod = true;
                    $resolvedTier = $currentTier; // Grace period mempertahankan tier
                } else {
                    // Turun maksimal 1 tingkat
                    $resolvedTier = match ($currentTier) {
                        'infinity' => 'moon',
                        'moon' => 'sun',
                        default => 'sun',
                    };
                }
            }
        } else {
            // Naik atau tetap pada tier natural tertinggi
            $resolvedTier = $naturalTier;
        }

        $eligibleForBottleLocker = ($resolvedTier === 'infinity');

        // 3. Terbitkan Sertifikat Komputasi
        $signature = MembershipTierVerifier::sign(
            currentTier: $currentTier,
            spend365: $input->spend365DaysIdr,
            visits365: $input->visits365Days,
            isAnniversary: $input->isAnniversaryEvaluation,
            resolvedTier: $resolvedTier,
            inGrace: $inGracePeriod,
            eligibleLocker: $eligibleForBottleLocker
        );

        $certificate = new MembershipTierCertificate(
            currentTier: $currentTier,
            spend365DaysIdr: $input->spend365DaysIdr,
            visits365Days: $input->visits365Days,
            isAnniversaryEvaluation: $input->isAnniversaryEvaluation,
            resolvedTier: $resolvedTier,
            inGracePeriod: $inGracePeriod,
            eligibleForBottleLocker: $eligibleForBottleLocker,
            hmacSignature: $signature
        );

        return new MembershipTierResult(
            resolvedTier: $resolvedTier,
            inGracePeriod: $inGracePeriod,
            eligibleForBottleLocker: $eligibleForBottleLocker,
            certificate: $certificate
        );
    }

    /**
     * Hitung perolehan poin loyalitas berdasarkan nilai belanja dan tier keanggotaan.
     */
    public function calculatePoints(int $spendIdr, string $tier): int
    {
        if ($spendIdr <= 0) {
            return 0;
        }

        $basePoints = (int) intdiv($spendIdr, 10_000);

        $multiplierBps = match (strtolower($tier)) {
            'infinity' => 200, // 2.0x
            'moon' => 150,     // 1.5x
            default => 100,    // 1.0x
        };

        return (int) intdiv($basePoints * $multiplierBps, 100);
    }
}
