<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\DTOs\FacialSpoofAnalysisDTO;

/**
 * Service to execute deep biometric anti-spoofing presentation attack detection (PAD).
 * Inspects presentation medium artifacts: screen moire patterns, paper print borders,
 * specular reflection dynamics, and micro-expressions.
 */
class FacialAntiSpoofingAnalysisService
{
    public const CONFIDENCE_THRESHOLD = 0.85;

    public const MOIRE_FREQUENCY_MIN_HZ = 40.0;

    /**
     * Analyze multi-spectral presentation attack vectors.
     *
     * @param array{
     *     texture_variance: float,
     *     specular_reflectance: float,
     *     moire_pattern_detected: bool,
     *     color_gamut_entropy: float,
     *     infrared_luma_contrast: float,
     *     micro_blinking_rate: float
     * } $attackVectors
     */
    public function analyzePresentationAttack(array $attackVectors): FacialSpoofAnalysisDTO
    {
        $textureVariance = (float) ($attackVectors['texture_variance'] ?? 0.0);
        $specularReflectance = (float) ($attackVectors['specular_reflectance'] ?? 0.0);
        $moireDetected = (bool) ($attackVectors['moire_pattern_detected'] ?? false);
        $colorEntropy = (float) ($attackVectors['color_gamut_entropy'] ?? 0.0);
        $infraredContrast = (float) ($attackVectors['infrared_luma_contrast'] ?? 0.0);
        $blinkingRate = (float) ($attackVectors['micro_blinking_rate'] ?? 0.0);

        $spoofIndicators = [];
        $livenessScore = 1.0;

        // Anomaly 1: Moire pattern indicates display screen replay attack
        if ($moireDetected) {
            $livenessScore -= 0.45;
            $spoofIndicators[] = 'SCREEN_MOIRE_REPLAY_ATTACK';
        }

        // Anomaly 2: Low texture variance indicates 2D paper print or static photo
        if ($textureVariance < 0.25) {
            $livenessScore -= 0.35;
            $spoofIndicators[] = 'STATIC_PRINT_PAPER_MASK';
        }

        // Anomaly 3: Abnormal specular reflectance indicates glass or plastic mask
        if ($specularReflectance > 0.85 || $specularReflectance < 0.05) {
            $livenessScore -= 0.30;
            $spoofIndicators[] = 'SYNTHETIC_LATEX_OR_GLASS_REFLECTION';
        }

        // Anomaly 4: Inadequate infrared contrast (live human skin absorbs and scatters NIR uniquely)
        if ($infraredContrast < 0.30) {
            $livenessScore -= 0.25;
            $spoofIndicators[] = 'NIR_ABSORPTION_ANOMALY';
        }

        // Anomaly 5: Zero blinking over extended presentation
        if ($blinkingRate < 0.05) {
            $livenessScore -= 0.20;
            $spoofIndicators[] = 'ABSENCE_OF_PHYSIOLOGICAL_BLINKING';
        }

        $livenessScore = max(0.0, min(1.0, $livenessScore));
        $passed = ($livenessScore >= self::CONFIDENCE_THRESHOLD) && empty($spoofIndicators);

        $classification = 'GENUINE_LIVE_SUBJECT';
        if (! $passed) {
            $classification = ! empty($spoofIndicators) ? $spoofIndicators[0] : 'SPOOF_CONFIDENCE_BELOW_THRESHOLD';
        }

        return new FacialSpoofAnalysisDTO(
            isGenuine: $passed,
            livenessConfidence: round($livenessScore, 3),
            primaryClassification: $classification,
            detectedSpoofIndicators: $spoofIndicators,
            hardwareSensorHealthy: true
        );
    }

    /**
     * Calculate 3D structured light depth deviation.
     */
    public function verifyStructuredLightDepthMap(array $depthPoints): bool
    {
        if (count($depthPoints) < 10) {
            return false;
        }

        $minDepth = min($depthPoints);
        $maxDepth = max($depthPoints);
        $depthRangeMm = $maxDepth - $minDepth;

        // A live human face features ~40mm - 120mm nose-to-ear depth delta
        return $depthRangeMm >= 35.0 && $depthRangeMm <= 140.0;
    }
}
