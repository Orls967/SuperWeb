<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Formal certificate verifier for Table Prefix Ownership.
 * Proves well-formedness, prefix uniqueness (no PREFIX_CONFLICT), and longest-match invariant (R4-ALG-02).
 */
final class PrefixCertificateVerifier
{
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

