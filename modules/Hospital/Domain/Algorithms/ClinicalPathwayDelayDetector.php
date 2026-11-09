<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Algorithms;

use RuntimeException;

/**
 * B3: ClinicalPathwayDelayDetector.
 * Deteksi keterlambatan clinical pathway berurutan dan alert tepat 1 kali.
 * Kompleksitas: O(V + E).
 */
final class ClinicalPathwayDelayDetector
{
    public const DEFAULT_TOLERANCE_MINUTES = 15;

    /**
     * @param list<array{
     *     id: string,
     *     order_type: string,
     *     planned_start_minute: int,
     *     duration_minute: int,
     *     prerequisites: list<string>,
     *     actual_start_minute?: ?int
     * }> $orders
     */
    public function detectDelays(array $orders, int $toleranceMinutes = self::DEFAULT_TOLERANCE_MINUTES): PathwayDelayResult
    {
        $orderMap = [];
        foreach ($orders as $o) {
            $orderMap[$o['id']] = $o;
        }

        $completions = []; // id => projected_completion_minute
        $projectedStarts = []; // id => projected_start_minute
        $alertTriggered = null; // ?array{id: string, delay_minutes: int}

        foreach ($orders as $o) {
            $id = $o['id'];
            $plannedStart = $o['planned_start_minute'];
            $duration = $o['duration_minute'];
            $actualStart = $o['actual_start_minute'] ?? null;

            // Hitung max selesai prasyarat
            $maxPrereqEnd = 0;
            foreach ($o['prerequisites'] as $prereqId) {
                if (isset($completions[$prereqId])) {
                    $maxPrereqEnd = max($maxPrereqEnd, $completions[$prereqId]);
                }
            }

            $effectiveStart = max($plannedStart, $maxPrereqEnd);
            if ($actualStart !== null) {
                $effectiveStart = $actualStart;
            }

            $projectedStarts[$id] = $effectiveStart;
            $completions[$id] = $effectiveStart + $duration;

            // Cek keterlambatan relatif terhadap rencana awal
            $delay = $effectiveStart - $plannedStart;

            // Alert dikirim SEKALI untuk order pertama yang terlambat > toleransi
            if ($alertTriggered === null && $delay > $toleranceMinutes) {
                $alertTriggered = [
                    'order_id' => $id,
                    'order_type' => $o['order_type'],
                    'delay_minutes' => $delay,
                ];
            }
        }

        $certificate = new ClinicalPathwayScheduleCertificate(
            orderCount: count($orders),
            alert: $alertTriggered,
            projectedStarts: $projectedStarts,
            projectedCompletions: $completions,
            generatedAt: time()
        );

        PathwayScheduleCertificateVerifier::verify($certificate);

        return new PathwayDelayResult($projectedStarts, $completions, $alertTriggered, $certificate);
    }
}

final class PathwayDelayResult
{
    /**
     * @param  array<string, int>  $projectedStarts
     * @param  array<string, int>  $projectedCompletions
     * @param  ?array{order_id: string, order_type: string, delay_minutes: int}  $alert
     */
    public function __construct(
        public readonly array $projectedStarts,
        public readonly array $projectedCompletions,
        public readonly ?array $alert,
        public readonly ClinicalPathwayScheduleCertificate $certificate
    ) {}
}

final class ClinicalPathwayScheduleCertificate
{
    public readonly string $sha256;

    /**
     * @param  ?array{order_id: string, order_type: string, delay_minutes: int}  $alert
     * @param  array<string, int>  $projectedStarts
     * @param  array<string, int>  $projectedCompletions
     */
    public function __construct(
        public readonly int $orderCount,
        public readonly ?array $alert,
        public readonly array $projectedStarts,
        public readonly array $projectedCompletions,
        public readonly int $generatedAt,
        ?string $customSha256 = null
    ) {
        $payload = json_encode([
            'count' => $this->orderCount,
            'alert' => $this->alert,
            'starts' => $this->projectedStarts,
            'completions' => $this->projectedCompletions,
        ], JSON_THROW_ON_ERROR);

        $this->sha256 = $customSha256 ?? hash('sha256', $payload);
    }
}

final class PathwayScheduleCertificateVerifier
{
    public static function verify(ClinicalPathwayScheduleCertificate $certificate): void
    {
        $payload = json_encode([
            'count' => $certificate->orderCount,
            'alert' => $certificate->alert,
            'starts' => $certificate->projectedStarts,
            'completions' => $certificate->projectedCompletions,
        ], JSON_THROW_ON_ERROR);

        $expectedHash = hash('sha256', $payload);
        if (! hash_equals($expectedHash, $certificate->sha256)) {
            throw new RuntimeException('Sertifikat jadwal pathway tidak valid: hash tidak cocok.');
        }
    }
}
