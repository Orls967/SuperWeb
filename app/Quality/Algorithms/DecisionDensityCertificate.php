<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Sertifikat Kriptografis Kepadatan Keputusan Logika A2 (W8-D03).
 */
final class DecisionDensityCertificate
{
    /**
     * @param  array<string, int>  $branchDistribution
     */
    public function __construct(
        public readonly string $sourceHash,
        public readonly int $decisionPoints,
        public readonly int $executableStatements,
        public readonly float $density,
        public readonly int $methodCount,
        public readonly int $propertyCount,
        public readonly bool $isAnemic,
        public readonly array $branchDistribution,
        public readonly string $signature,
        public readonly string $issuedAt
    ) {}

    public static function create(
        string $sourceHash,
        int $decisionPoints,
        int $executableStatements,
        int $methodCount,
        int $propertyCount,
        array $branchDistribution = []
    ): self {
        $density = $executableStatements > 0
            ? round((float) ($decisionPoints / $executableStatements), 4)
            : 0.0;

        $isAnemic = $decisionPoints === 0 && ($propertyCount >= 3 || $methodCount === 0);
        $issued = date('c');

        $payload = json_encode([
            'source_hash' => $sourceHash,
            'decision_points' => $decisionPoints,
            'statements' => $executableStatements,
            'density' => $density,
            'methods' => $methodCount,
            'properties' => $propertyCount,
            'is_anemic' => $isAnemic,
            'branches' => $branchDistribution,
            'issued_at' => $issued,
        ], JSON_THROW_ON_ERROR);

        $sig = hash_hmac('sha256', (string) $payload, 'PlatformQualitySecretKey_A2');

        return new self(
            sourceHash: $sourceHash,
            decisionPoints: $decisionPoints,
            executableStatements: $executableStatements,
            density: $density,
            methodCount: $methodCount,
            propertyCount: $propertyCount,
            isAnemic: $isAnemic,
            branchDistribution: $branchDistribution,
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
            'decision_points' => $this->decisionPoints,
            'executable_statements' => $this->executableStatements,
            'density' => $this->density,
            'method_count' => $this->methodCount,
            'property_count' => $this->propertyCount,
            'is_anemic' => $this->isAnemic,
            'branch_distribution' => $this->branchDistribution,
            'signature' => $this->signature,
            'issued_at' => $this->issuedAt,
        ];
    }
}
