<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\CarbonImmutable;
use RuntimeException;

final class GateAgeVerificationPdpService
{
    public function __construct(
        private readonly string $hmacSecret = 'ven-pdp-gate-secret-key-2026'
    ) {}

    /**
     * Verify age compliance against venue requirements without storing raw Date of Birth.
     * Complies with PDP (Perlindungan Data Pribadi) standards.
     *
     * @return array{
     *     is_eligible: bool,
     *     required_minimum_age: int,
     *     pdp_compliance_token: string,
     *     rejection_reason: ?string
     * }
     */
    public function evaluateAgeEligibility(int $attendeeAge, int $minimumAgeRequirement): array
    {
        $isEligible = $attendeeAge >= $minimumAgeRequirement;
        $timestamp = CarbonImmutable::now()->getTimestamp();

        $payload = json_encode([
            'eligible' => $isEligible,
            'min_age' => $minimumAgeRequirement,
            'ts' => $timestamp,
        ], JSON_THROW_ON_ERROR);

        $token = hash_hmac('sha256', (string) $payload, $this->hmacSecret);

        $rejectionReason = null;
        if (!$isEligible) {
            $rejectionReason = "Usia tidak mencukupi batas legal venue (Minimal {$minimumAgeRequirement} tahun). Akses ditolak.";
        }

        return [
            'is_eligible' => $isEligible,
            'required_minimum_age' => $minimumAgeRequirement,
            'pdp_compliance_token' => $token,
            'rejection_reason' => $rejectionReason,
        ];
    }

    /**
     * Verify authenticity of a presented PDP token without inspecting any personal identity records.
     */
    public function verifyToken(string $token, bool $expectedEligible, int $minAge, int $timestamp): bool
    {
        $payload = json_encode([
            'eligible' => $expectedEligible,
            'min_age' => $minAge,
            'ts' => $timestamp,
        ], JSON_THROW_ON_ERROR);

        $expected = hash_hmac('sha256', (string) $payload, $this->hmacSecret);

        return hash_equals($expected, $token);
    }
}
