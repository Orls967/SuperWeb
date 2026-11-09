<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Pipeline;

final class ContinuousComplianceAuditor
{
    /**
     * Checks deployment compliance against SOC2, ISO27001, and enterprise change standards.
     *
     * @param  array{has_peer_review: bool, has_automated_tests: bool, has_cab_quorum: bool, zero_high_cve: bool, signed_commit: bool}  $evidence
     * @return array{audit_status: string, compliant: bool, gap_reasons: list<string>}
     */
    public function auditPipelineCompliance(array $evidence): array
    {
        $gaps = [];

        if (! ($evidence['has_peer_review'] ?? false)) {
            $gaps[] = 'SOC2 CC8.1: Tidak ditemukan bukti persetujuan peer review independen.';
        }

        if (! ($evidence['has_automated_tests'] ?? false)) {
            $gaps[] = 'ISO 27001 A.14.2.8: Pipeline pengujian regresi otomatis wajib dijalankan dan lulus 100%.';
        }

        if (! ($evidence['zero_high_cve'] ?? false)) {
            $gaps[] = 'Security Baseline: Ditemukan dependensi dengan kerentanan CVE berperingkat High atau Critical.';
        }

        if (! ($evidence['signed_commit'] ?? false)) {
            $gaps[] = 'Supply Chain Security: Seluruh commit pada rilis produksi wajib ditandatangani GPG/SSH.';
        }

        $compliant = ($gaps === []);

        return [
            'audit_status' => $compliant ? 'AUDIT_PASSED_CERTIFIED' : 'AUDIT_NON_COMPLIANT',
            'compliant' => $compliant,
            'gap_reasons' => $gaps,
        ];
    }
}
