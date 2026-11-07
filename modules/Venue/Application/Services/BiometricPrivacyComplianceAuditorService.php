<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\BiometricCredential;

/**
 * Service to execute periodic compliance auditing over stored biometric credentials.
 * Ensures zero plaintext face embeddings reside in persistent storage.
 */
class BiometricPrivacyComplianceAuditorService
{
    /**
     * Audit stored biometric credentials against cryptographic zeroization standards.
     *
     * @return array{
     *     total_credentials_checked: int,
     *     unencrypted_anomalies_detected: int,
     *     missing_consent_records: int,
     *     outdated_consent_versions: int,
     *     compliance_percentage: float,
     *     audit_verdict: string,
     *     remediation_actions: array<int, string>
     * }
     */
    public function auditStoredCredentials(): array
    {
        $credentials = BiometricCredential::all();
        $total = $credentials->count();

        if ($total === 0) {
            return [
                'total_credentials_checked' => 0,
                'unencrypted_anomalies_detected' => 0,
                'missing_consent_records' => 0,
                'outdated_consent_versions' => 0,
                'compliance_percentage' => 100.0,
                'audit_verdict' => 'CLEAN_EMPTY_VAULT',
                'remediation_actions' => [],
            ];
        }

        $anomalies = 0;
        $missingConsent = 0;
        $outdatedVersions = 0;
        $remediations = [];

        foreach ($credentials as $cred) {
            $hash = (string) $cred->template_hash;

            // Template hash must be 64-char hex SHA256 string, never JSON or raw floats
            if (strlen($hash) !== 64 || ! ctype_xdigit($hash)) {
                $anomalies++;
                $remediations[] = sprintf('Credential #%d violates format. Purge immediately.', $cred->id);
            }

            if ($cred->consent_given_at === null && $cred->status === 'ACTIVE') {
                $missingConsent++;
                $remediations[] = sprintf('Credential #%d lacks consent timestamp.', $cred->id);
            }

            if ($cred->privacy_consent_version !== 'UU-PDP-2022-V2' && $cred->status === 'ACTIVE') {
                $outdatedVersions++;
            }
        }

        $violatingCount = $anomalies + $missingConsent;
        $compliancePct = round((($total - $violatingCount) / $total) * 100.0, 2);

        $verdict = 'COMPLIANT_HEALTHY';
        if ($anomalies > 0) {
            $verdict = 'CRITICAL_SECURITY_BREACH_DETECTED';
        } elseif ($missingConsent > 0 || $compliancePct < 98.0) {
            $verdict = 'ACTION_REQUIRED_REGULATORY_GAP';
        }

        return [
            'total_credentials_checked' => $total,
            'unencrypted_anomalies_detected' => $anomalies,
            'missing_consent_records' => $missingConsent,
            'outdated_consent_versions' => $outdatedVersions,
            'compliance_percentage' => $compliancePct,
            'audit_verdict' => $verdict,
            'remediation_actions' => $remediations,
        ];
    }
}
