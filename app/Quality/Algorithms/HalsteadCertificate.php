<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Sertifikat Kriptografis Kompleksitas Perangkat Lunak Halstead (W8-D02).
 */
final class HalsteadCertificate
{
    public function __construct(
        public readonly string $sourceHash,
        public readonly int $uniqueOperators,
        public readonly int $uniqueOperands,
        public readonly int $totalOperators,
        public readonly int $totalOperands,
        public readonly float $volume,
        public readonly float $difficulty,
        public readonly float $effort,
        public readonly float $timeSeconds,
        public readonly float $estimatedBugs,
        public readonly string $signature,
        public readonly string $issuedAt
    ) {
    }

    public static function create(
        string $sourceHash,
        int $eta1,
        int $eta2,
        int $n1,
        int $n2,
        float $volume,
        float $difficulty,
        float $effort,
        float $timeSec,
        float $bugs
    ): self {
        $issued = date('c');

        $payload = json_encode([
            'source_hash' => $sourceHash,
            'eta1' => $eta1,
            'eta2' => $eta2,
            'n1' => $n1,
            'n2' => $n2,
            'volume' => $volume,
            'difficulty' => $difficulty,
            'effort' => $effort,
            'time_sec' => $timeSec,
            'bugs' => $bugs,
            'issued_at' => $issued,
        ], JSON_THROW_ON_ERROR);

        $sig = hash_hmac('sha256', (string) $payload, 'PlatformQualitySecretKey_Halstead');

        return new self(
            sourceHash: $sourceHash,
            uniqueOperators: $eta1,
            uniqueOperands: $eta2,
            totalOperators: $n1,
            totalOperands: $n2,
            volume: $volume,
            difficulty: $difficulty,
            effort: $effort,
            timeSeconds: $timeSec,
            estimatedBugs: $bugs,
            signature: $sig,
            issuedAt: $issued
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_hash' => $this->sourceHash,
            'unique_operators' => $this->uniqueOperators,
            'unique_operands' => $this->uniqueOperands,
            'total_operators' => $this->totalOperators,
            'total_operands' => $this->totalOperands,
            'volume' => $this->volume,
            'difficulty' => $this->difficulty,
            'effort' => $this->effort,
            'time_seconds' => $this->timeSeconds,
            'estimated_bugs' => $this->estimatedBugs,
            'signature' => $this->signature,
            'issued_at' => $this->issuedAt,
        ];
    }
}

