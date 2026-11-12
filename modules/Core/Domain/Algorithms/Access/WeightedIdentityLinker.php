<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Access;

/**
 * Weighted multi-party identity linker algorithm with double-consent enforcement (W8-D56, C2).
 *
 * Scoring weights:
 * - NIK HMAC Hash Match: 100 points
 * - Email HMAC Hash Match: 60 points
 * - Phone HMAC Hash Match: 50 points
 * - Normalized Name + DOB Hash Match: 40 points
 *
 * Qualification threshold: Total score >= 90 points.
 * Consent requirement: Both source_consent and target_consent must be true, else BLOCKED_NO_CONSENT.
 */
class WeightedIdentityLinker
{
    public const WEIGHT_NIK = 100;

    public const WEIGHT_EMAIL = 60;

    public const WEIGHT_PHONE = 50;

    public const WEIGHT_NAME_DOB = 40;

    public const THRESHOLD_LINKAGE = 90;

    public function __construct(
        private readonly string $secretSalt = 'superweb_identity_salt_2026'
    ) {}

    /**
     * Evaluate linkage between two identity subjects.
     *
     * @param  array<string, mixed>  $subjectA
     * @param  array<string, mixed>  $subjectB
     * @return array{match_score: int, is_linked: bool, status: string, matched_attributes: list<string>, certificate: array<string, mixed>}
     */
    public function evaluateLinkage(
        array $subjectA,
        array $subjectB,
        bool $sourceConsent,
        bool $targetConsent
    ): array {
        $matchedAttributes = [];
        $score = 0;

        // 1. NIK Match (100)
        $nikA = $this->normalizeNik((string) ($subjectA['nik'] ?? ''));
        $nikB = $this->normalizeNik((string) ($subjectB['nik'] ?? ''));
        if ($nikA !== '' && $nikB !== '' && hash_equals($this->hashAttribute($nikA), $this->hashAttribute($nikB))) {
            $score += self::WEIGHT_NIK;
            $matchedAttributes[] = 'nik';
        }

        // 2. Email Match (60)
        $emailA = $this->normalizeEmail((string) ($subjectA['email'] ?? ''));
        $emailB = $this->normalizeEmail((string) ($subjectB['email'] ?? ''));
        if ($emailA !== '' && $emailB !== '' && hash_equals($this->hashAttribute($emailA), $this->hashAttribute($emailB))) {
            $score += self::WEIGHT_EMAIL;
            $matchedAttributes[] = 'email';
        }

        // 3. Phone Match (50)
        $phoneA = $this->normalizePhone((string) ($subjectA['phone'] ?? ''));
        $phoneB = $this->normalizePhone((string) ($subjectB['phone'] ?? ''));
        if ($phoneA !== '' && $phoneB !== '' && hash_equals($this->hashAttribute($phoneA), $this->hashAttribute($phoneB))) {
            $score += self::WEIGHT_PHONE;
            $matchedAttributes[] = 'phone';
        }

        // 4. Name + DOB Match (40)
        $nameDobA = $this->normalizeNameDob((string) ($subjectA['name_dob'] ?? ''));
        $nameDobB = $this->normalizeNameDob((string) ($subjectB['name_dob'] ?? ''));
        if ($nameDobA !== '' && $nameDobB !== '' && hash_equals($this->hashAttribute($nameDobA), $this->hashAttribute($nameDobB))) {
            $score += self::WEIGHT_NAME_DOB;
            $matchedAttributes[] = 'name_dob';
        }

        // Determine status
        if ($score < self::THRESHOLD_LINKAGE) {
            $status = 'REJECTED_BELOW_THRESHOLD';
            $isLinked = false;
        } elseif (! $sourceConsent || ! $targetConsent) {
            $status = 'BLOCKED_NO_CONSENT';
            $isLinked = false;
        } else {
            $status = 'LINKED';
            $isLinked = true;
        }

        $certificate = $this->generateCertificate(
            score: $score,
            status: $status,
            isLinked: $isLinked,
            matchedAttributes: $matchedAttributes,
            sourceConsent: $sourceConsent,
            targetConsent: $targetConsent
        );

        return [
            'match_score' => $score,
            'is_linked' => $isLinked,
            'status' => $status,
            'matched_attributes' => $matchedAttributes,
            'certificate' => $certificate,
        ];
    }

    public function hashAttribute(string $raw): string
    {
        return hash_hmac('sha256', $raw, $this->secretSalt);
    }

    private function normalizeNik(string $nik): string
    {
        return preg_replace('/\D/', '', trim($nik)) ?? '';
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function normalizePhone(string $phone): string
    {
        $clean = preg_replace('/\D/', '', trim($phone)) ?? '';
        if (str_starts_with($clean, '0')) {
            $clean = '62'.substr($clean, 1);
        }
        if (! str_starts_with($clean, '+') && $clean !== '') {
            $clean = '+'.$clean;
        }

        return $clean;
    }

    private function normalizeNameDob(string $nameDob): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $nameDob) ?? ''));
    }

    /**
     * @param  list<string>  $matchedAttributes
     * @return array<string, mixed>
     */
    private function generateCertificate(
        int $score,
        string $status,
        bool $isLinked,
        array $matchedAttributes,
        bool $sourceConsent,
        bool $targetConsent
    ): array {
        $payload = [
            'algorithm' => 'WEIGHTED_IDENTITY_LINKER_V1',
            'match_score' => $score,
            'status' => $status,
            'is_linked' => $isLinked,
            'matched_attributes' => $matchedAttributes,
            'source_consent' => $sourceConsent,
            'target_consent' => $targetConsent,
            'timestamp' => time(),
        ];

        $canonicalJson = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $payload['sha256'] = hash('sha256', $canonicalJson);

        return $payload;
    }
}
