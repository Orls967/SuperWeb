<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Sertifikat Kriptografis Hasil Analisis Duplikasi Kode A1 (W8-D02).
 */
final class DuplicateWindowCertificate
{
    /**
     * @param list<array{fileA: string, indexA: int, fileB: string, indexB: int, length: int}> $blocks
     */
    public function __construct(
        public readonly string $sourceHash,
        public readonly int $windowSize,
        public readonly int $basis,
        public readonly int $modulus,
        public readonly int $totalTokensAnalyzed,
        public readonly int $duplicateTokenCount,
        public readonly float $duplicateRatio,
        public readonly array $blocks,
        public readonly string $signature,
        public readonly string $issuedAt
    ) {
    }

    public static function create(
        string $sourceHash,
        int $windowSize,
        int $totalTokens,
        int $dupTokens,
        array $blocks,
        int $basis = DuplicateWindowDetector::BASIS,
        int $modulus = DuplicateWindowDetector::DEFAULT_MODULUS
    ): self {
        $ratio = $totalTokens > 0 ? (float) ($dupTokens / $totalTokens) : 0.0;
        $issued = date('c');

        $payload = json_encode([
            'source_hash' => $sourceHash,
            'window_size' => $windowSize,
            'basis' => $basis,
            'modulus' => $modulus,
            'total_tokens' => $totalTokens,
            'dup_tokens' => $dupTokens,
            'dup_ratio' => round($ratio, 6),
            'blocks' => $blocks,
            'issued_at' => $issued,
        ], JSON_THROW_ON_ERROR);

        $sig = hash_hmac('sha256', (string) $payload, 'PlatformQualitySecretKey_A1');

        return new self(
            sourceHash: $sourceHash,
            windowSize: $windowSize,
            basis: $basis,
            modulus: $modulus,
            totalTokensAnalyzed: $totalTokens,
            duplicateTokenCount: $dupTokens,
            duplicateRatio: round($ratio, 6),
            blocks: $blocks,
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
            'window_size' => $this->windowSize,
            'basis' => $this->basis,
            'modulus' => $this->modulus,
            'total_tokens' => $this->totalTokensAnalyzed,
            'duplicate_tokens' => $this->duplicateTokenCount,
            'duplicate_ratio' => $this->duplicateRatio,
            'blocks' => $this->blocks,
            'signature' => $this->signature,
            'issued_at' => $this->issuedAt,
        ];
    }
}

