<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use InvalidArgumentException;
use RuntimeException;

/**
 * Algoritma E14: Penjadwalan Sekuensial Black-Start & Sinkronisasi Grid (Topological Sorting).
 * Menyusun urutan langkah penyalaan mandiri komponen microgrid pasca total blackout,
 * mencegah arus inrush berlebih dan memastikan kestabilan tegangan/frekuensi sebelum sinkronisasi.
 */
final class BlackStartSynchronizationSequencer
{
    /**
     * @param list<array{
     *     code: string,
     *     name: string,
     *     dependencies: list<string>,
     *     step_duration_seconds: int,
     *     inrush_current_amp: int
     * }> $components
     */
    public function planSequence(string $microgridCode, array $components): BlackStartSynchronizationResult
    {
        if (empty($components)) {
            throw new InvalidArgumentException('Daftar komponen black-start tidak boleh kosong.');
        }

        $componentMap = [];
        $inDegree = [];
        $adjacencyList = [];

        foreach ($components as $c) {
            $code = $c['code'];
            $componentMap[$code] = $c;
            $inDegree[$code] = count($c['dependencies']);
            $adjacencyList[$code] = [];
        }

        foreach ($components as $c) {
            $code = $c['code'];
            foreach ($c['dependencies'] as $dep) {
                if (! isset($componentMap[$dep])) {
                    throw new InvalidArgumentException("Dependensi '{$dep}' untuk komponen '{$code}' tidak ditemukan.");
                }
                $adjacencyList[$dep][] = $code;
            }
        }

        // Kahn's Algorithm dengan priority queue (min-heap / alfabetis) untuk determinisme
        $queue = [];
        foreach ($inDegree as $code => $deg) {
            if ($deg === 0) {
                $queue[] = $code;
            }
        }
        sort($queue);

        $orderedCodes = [];
        while (! empty($queue)) {
            $curr = array_shift($queue);
            $orderedCodes[] = $curr;

            foreach ($adjacencyList[$curr] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                    sort($queue);
                }
            }
        }

        if (count($orderedCodes) !== count($components)) {
            throw new RuntimeException('Terdeteksi siklus ketergantungan melingkar pada rencana black-start.');
        }

        $sequence = [];
        $totalSeconds = 0;
        foreach ($orderedCodes as $idx => $code) {
            $comp = $componentMap[$code];
            $stepDuration = (int) $comp['step_duration_seconds'];
            $totalSeconds += $stepDuration;

            $sequence[] = [
                'step' => $idx + 1,
                'component_code' => $code,
                'component_name' => $comp['name'],
                'estimated_seconds' => $stepDuration,
                'inrush_current_amp' => (int) $comp['inrush_current_amp'],
            ];
        }

        $seqCodes = implode('->', $orderedCodes);
        $canonicalPayload = sprintf(
            'BLACK-START|GRID:%s|COUNT:%d|TOTAL_SEC:%d|SEQ:[%s]',
            $microgridCode,
            count($components),
            $totalSeconds,
            $seqCodes
        );

        $certificateHash = hash('sha256', $canonicalPayload);

        return new BlackStartSynchronizationResult(
            microgridCode: $microgridCode,
            totalComponentsCount: count($components),
            totalEstimatedSeconds: $totalSeconds,
            sequence: $sequence,
            certificateHash: $certificateHash,
            canonicalPayload: $canonicalPayload
        );
    }
}
