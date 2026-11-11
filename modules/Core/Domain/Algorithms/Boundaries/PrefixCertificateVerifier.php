<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

use InvalidArgumentException;
use Modules\Core\Domain\Algorithms\CanonicalCertificateSupport;

/**
 * Formal certificate verifier for Table Prefix Ownership.
 * Complexity: O(P) per lookup where P <= 500 registered prefixes. Integer/string comparisons only.
 * Proves well-formedness, prefix uniqueness (no PREFIX_CONFLICT), longest-match invariant,
 * and SHA-256 canonical certificate integrity (R4-ALG-02).
 */
final class PrefixCertificateVerifier
{
    /**
     * Build canonical SHA-256 certificate for prefix ownership resolution.
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>  $steps
     * @param  array<string, mixed>  $result
     * @return array{input: array<string, mixed>, steps: list<string>, result: array<string, mixed>, sha256: string}
     */
    public function issueCertificate(array $input, array $steps, array $result): array
    {
        return CanonicalCertificateSupport::issue($input, $steps, $result);
    }

    /**
     * Verify canonical SHA-256 certificate for table prefix resolution; throws on corruption.
     *
     * @param  array<string, mixed>  $certificate
     */
    public function verifyCertificate(array $certificate): bool
    {
        $verified = CanonicalCertificateSupport::verifyIntegrity($certificate, 'PREFIX_CERT');
        $prefixes = $verified['input']['prefixes'] ?? null;

        if (! is_array($prefixes) || ! $this->verifyPrefixes($prefixes)) {
            throw new InvalidArgumentException('PREFIX_CERT_INVARIANT_FAILED: Daftar prefiks pada sertifikat tidak sah.');
        }

        return true;
    }

    /**
     * Verify that all table prefixes are well-formed and unique.
     *
     * @param  array<string, string>  $prefixes  prefix => owner
     */
    public function verifyPrefixes(array $prefixes): bool
    {
        if ($prefixes === []) {
            return false;
        }

        $seenPrefixes = [];

        foreach ($prefixes as $prefix => $owner) {
            // Must be lowercase alphanumeric with underscore, e.g. 'core_' or 'hcm_pay_'
            if (preg_match('/^[a-z0-9_]+_$/', $prefix) !== 1) {
                return false;
            }

            if (isset($seenPrefixes[$prefix])) {
                return false; // Duplicate prefix (PREFIX_CONFLICT)
            }

            $seenPrefixes[$prefix] = true;

            if (trim($owner) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Verify longest prefix invariant on candidate matches.
     *
     * @param  list<string>  $candidatePrefixes
     */
    public function verifyLongestMatch(string $table, array $candidatePrefixes, string $selectedPrefix): bool
    {
        $matching = [];

        foreach ($candidatePrefixes as $prefix) {
            if (str_starts_with($table, $prefix)) {
                $matching[] = $prefix;
            }
        }

        if ($matching === []) {
            return false;
        }

        // Longest length must be selected
        $maxLength = 0;
        foreach ($matching as $p) {
            if (strlen($p) > $maxLength) {
                $maxLength = strlen($p);
            }
        }

        return strlen($selectedPrefix) === $maxLength && in_array($selectedPrefix, $matching, true);
    }
}
