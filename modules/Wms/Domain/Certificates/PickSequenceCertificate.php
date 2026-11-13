<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Certificates;

/**
 * Sertifikat lintasan picking S-Shape vs Largest-Gap (H2).
 */
final readonly class PickSequenceCertificate
{
    /**
     * @param  list<array{aisle: int, pos: int, bin_id: string}>  $sequence
     */
    public function __construct(
        public string $strategy,
        public int $totalDistance,
        public int $sShapeDistance,
        public int $largestGapDistance,
        public int $inputPickCount,
        public array $sequence,
        public string $signatureHash,
    ) {}

    /**
     * @param  list<array{aisle: int, pos: int, bin_id: string}>  $sequence
     */
    public static function create(
        string $strategy,
        int $totalDistance,
        int $sShapeDistance,
        int $largestGapDistance,
        int $inputPickCount,
        array $sequence,
    ): self {
        $payload = json_encode([
            'strategy' => $strategy,
            'total_distance' => $totalDistance,
            's_shape' => $sShapeDistance,
            'largest_gap' => $largestGapDistance,
            'sequence' => $sequence,
        ], JSON_THROW_ON_ERROR);

        return new self(
            strategy: $strategy,
            totalDistance: $totalDistance,
            sShapeDistance: $sShapeDistance,
            largestGapDistance: $largestGapDistance,
            inputPickCount: $inputPickCount,
            sequence: $sequence,
            signatureHash: hash('sha256', $payload),
        );
    }
}
