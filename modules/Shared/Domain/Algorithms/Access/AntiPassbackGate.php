<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Access;

use RuntimeException;

/**
 * Algoritma V2: AntiPassbackGate
 *
 * Status per tiket bergantian MASUK/KELUAR. Re-entry maksimal N (default fixture 2).
 * Nonce ulang = replay ditolak.
 * Menghasilkan AntiPassbackCertificate dengan verifikasi urutan hash-chain utuh.
 */
final class AntiPassbackGate
{
    /**
     * @param  list<array{direction: string, nonce: string, timestamp: string, hash: string}>  $history
     * @return array{
     *     allowed: bool,
     *     reason: ?string,
     *     reentry_count: int,
     *     direction: string,
     *     new_hash: string,
     *     history: list<array{direction: string, nonce: string, timestamp: string, hash: string}>,
     *     certificate: ?AntiPassbackCertificate
     * }
     */
    public static function evaluate(
        string $ticketId,
        string $direction,
        string $nonce,
        string $timestamp,
        array $history,
        int $maxReentry = 2
    ): array {
        $direction = strtoupper($direction);
        if ($direction !== 'IN' && $direction !== 'OUT') {
            throw new RuntimeException("Direction must be IN or OUT, got: {$direction}");
        }

        // 1. Anti-Replay: cek apakah nonce sudah pernah dipakai
        foreach ($history as $scan) {
            if ($scan['nonce'] === $nonce) {
                return [
                    'allowed' => false,
                    'reason' => 'REPLAY_ATTACK',
                    'reentry_count' => self::countReentries($history),
                    'direction' => $direction,
                    'new_hash' => end($history)['hash'] ?? '',
                    'history' => $history,
                    'certificate' => null,
                ];
            }
        }

        $lastScan = end($history);
        $lastDirection = $lastScan ? $lastScan['direction'] : null;

        // 2. Anti-Passback: urutan harus bergantian
        if ($direction === 'IN') {
            if ($lastDirection === 'IN') {
                return [
                    'allowed' => false,
                    'reason' => 'PASSBACK_VIOLATION',
                    'reentry_count' => self::countReentries($history),
                    'direction' => $direction,
                    'new_hash' => $lastScan['hash'] ?? '',
                    'history' => $history,
                    'certificate' => null,
                ];
            }

            // Hitung re-entry: jumlah IN sebelumnya
            $reentryCount = self::countReentries($history);
            if ($lastDirection !== null) { // Jika bukan pindaian pertama, ini adalah re-entry
                $reentryCount++;
                if ($reentryCount > $maxReentry) {
                    return [
                        'allowed' => false,
                        'reason' => 'REENTRY_LIMIT_EXCEEDED',
                        'reentry_count' => $reentryCount,
                        'direction' => $direction,
                        'new_hash' => $lastScan['hash'] ?? '',
                        'history' => $history,
                        'certificate' => null,
                    ];
                }
            }
        } else { // direction === 'OUT'
            if ($lastDirection !== 'IN') {
                return [
                    'allowed' => false,
                    'reason' => 'OUT_WITHOUT_IN',
                    'reentry_count' => self::countReentries($history),
                    'direction' => $direction,
                    'new_hash' => $lastScan['hash'] ?? '',
                    'history' => $history,
                    'certificate' => null,
                ];
            }
            $reentryCount = self::countReentries($history);
        }

        // Pindaian lolos -> hitung hash-chain baru
        $prevHash = $lastScan['hash'] ?? hash('sha256', "TICKET:{$ticketId}");
        $newHash = hash('sha256', "{$prevHash}:{$direction}:{$nonce}:{$timestamp}");

        $updatedHistory = $history;
        $updatedHistory[] = [
            'direction' => $direction,
            'nonce' => $nonce,
            'timestamp' => $timestamp,
            'hash' => $newHash,
        ];

        $netInMinusOut = self::calculateNet($updatedHistory);

        $signature = AntiPassbackCertificateVerifier::sign(
            $ticketId,
            $maxReentry,
            $reentryCount,
            $direction,
            $netInMinusOut,
            $updatedHistory
        );

        $certificate = new AntiPassbackCertificate(
            ticketId: $ticketId,
            maxReentry: $maxReentry,
            reentryCount: $reentryCount,
            currentDirection: $direction,
            netInMinusOut: $netInMinusOut,
            history: $updatedHistory,
            hmacSignature: $signature
        );

        AntiPassbackCertificateVerifier::verify($certificate);

        return [
            'allowed' => true,
            'reason' => null,
            'reentry_count' => $reentryCount,
            'direction' => $direction,
            'new_hash' => $newHash,
            'history' => $updatedHistory,
            'certificate' => $certificate,
        ];
    }

    /**
     * @param  list<array{direction: string, nonce: string, timestamp: string, hash: string}>  $history
     */
    public static function countReentries(array $history): int
    {
        $inCount = 0;
        foreach ($history as $scan) {
            if ($scan['direction'] === 'IN') {
                $inCount++;
            }
        }

        return max(0, $inCount - 1);
    }

    /**
     * @param  list<array{direction: string, nonce: string, timestamp: string, hash: string}>  $history
     */
    public static function calculateNet(array $history): int
    {
        $in = 0;
        $out = 0;
        foreach ($history as $scan) {
            if ($scan['direction'] === 'IN') {
                $in++;
            } elseif ($scan['direction'] === 'OUT') {
                $out++;
            }
        }

        return $in - $out;
    }
}
