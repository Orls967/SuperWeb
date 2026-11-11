<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Values;

use InvalidArgumentException;

/**
 * Immutable value object representing multi-party identity matching metrics (W8-D56).
 *
 * Encapsulates weighted similarity calculations across attributes (NIK, phone, email, name, DOB).
 */
final class IdentityLinkScore
{
    public const BAND_DEFINITE = 'DEFINITE';   // >= 95%

    public const BAND_PROBABLE = 'PROBABLE';   // >= 85%

    public const BAND_POSSIBLE = 'POSSIBLE';   // >= 70%

    public const BAND_REJECTED = 'REJECTED';   // < 70%

    /**
     * @param  float  $overallScore  Aggregated match score from 0.0 to 1.0
     * @param  string  $confidenceBand  Confidence classification band
     * @param  array<string, float>  $attributeScores  Breakdown of score per evaluated attribute
     * @param  array<string, float>  $attributeWeights  Weight applied to each attribute
     * @param  bool  $hasDirectIdentifierMatch  Whether a cryptographic or exact primary key matched (e.g., NIK hash)
     * @param  bool  $sourceConsent  Granted consent from subject A
     * @param  bool  $targetConsent  Granted consent from subject B
     */
    public function __construct(
        public readonly float $overallScore,
        public readonly string $confidenceBand,
        public readonly array $attributeScores = [],
        public readonly array $attributeWeights = [],
        public readonly bool $hasDirectIdentifierMatch = false,
        public readonly bool $sourceConsent = false,
        public readonly bool $targetConsent = false
    ) {
        if ($overallScore < 0.0 || $overallScore > 1.0) {
            throw new InvalidArgumentException("Overall score must be 0.0 to 1.0, got: {$overallScore}");
        }
    }

    /**
     * Determine if linkage criteria are fully met (score >= 0.85 and double consent verified).
     */
    public function isLinkageEligible(): bool
    {
        return $this->overallScore >= 0.85 && $this->sourceConsent && $this->targetConsent;
    }

    /**
     * Determine if linkage is blocked due to missing consent despite high score.
     */
    public function isBlockedByMissingConsent(): bool
    {
        return $this->overallScore >= 0.85 && (! $this->sourceConsent || ! $this->targetConsent);
    }

    /**
     * Create an instance from attribute scores and weights.
     *
     * @param  array<string, float>  $scores
     * @param  array<string, float>  $weights
     */
    public static function compute(
        array $scores,
        array $weights,
        bool $hasDirectMatch,
        bool $sourceConsent,
        bool $targetConsent
    ): self {
        $totalWeight = 0.0;
        $weightedSum = 0.0;

        foreach ($scores as $attr => $score) {
            $weight = $weights[$attr] ?? 1.0;
            $weightedSum += ($score * $weight);
            $totalWeight += $weight;
        }

        $overall = $totalWeight > 0.0 ? round($weightedSum / $totalWeight, 4) : 0.0;

        $band = match (true) {
            $overall >= 0.95 => self::BAND_DEFINITE,
            $overall >= 0.85 => self::BAND_PROBABLE,
            $overall >= 0.70 => self::BAND_POSSIBLE,
            default => self::BAND_REJECTED,
        };

        return new self(
            overallScore: $overall,
            confidenceBand: $band,
            attributeScores: $scores,
            attributeWeights: $weights,
            hasDirectIdentifierMatch: $hasDirectMatch,
            sourceConsent: $sourceConsent,
            targetConsent: $targetConsent
        );
    }
}
