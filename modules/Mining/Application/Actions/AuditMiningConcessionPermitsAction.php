<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Modules\Mining\Domain\Models\MiningConcessionPermit;
use Modules\Mining\Domain\Models\MiningSite;

class AuditMiningConcessionPermitsAction
{
    /**
     * Audit komprehensif seluruh izin konsesi pertambangan minerba (D94-5, D94-6).
     *
     * Memvalidasi kepatuhan legalitas:
     * 1. IUP / IUPK Operasi Produksi (Ditjen Minerba ESDM)
     * 2. Persetujuan RKAB (Rencana Kerja dan Anggaran Biaya) tahun berjalan
     * 3. IPPKH / PPKH (Persetujuan Penggunaan Kawasan Hutan - KLHK)
     * 4. Izin Lingkungan AMDAL / UKL-UPL dan SLO IPAL
     * 5. Izin Penggunaan Handak (Bahan Peledak) P2 & Gudang Handak
     *
     * @return array{
     *     site_id: string,
     *     total_permits_audited: int,
     *     compliant_count: int,
     *     critical_warning_count: int,
     *     expired_count: int,
     *     is_fully_compliant: bool,
     *     audit_summary: list<array{
     *         permit_code: string,
     *         permit_type: string,
     *         issuing_authority: string,
     *         expires_at: ?string,
     *         days_remaining: ?int,
     *         compliance_status: string,
     *         action_required: ?string
     *     }>
     * }
     */
    public function execute(string $siteId, ?CarbonImmutable $asOfDate = null): array
    {
        $site = MiningSite::findOrFail($siteId);
        $asOf = $asOfDate ?? CarbonImmutable::now();

        /** @var Collection<int, MiningConcessionPermit> $permits */
        $permits = MiningConcessionPermit::where('site_id', $site->id)->get();

        $auditSummary = [];
        $compliantCount = 0;
        $criticalWarningCount = 0;
        $expiredCount = 0;

        foreach ($permits as $permit) {
            $expiresAt = $permit->valid_until ? CarbonImmutable::parse($permit->valid_until) : null;
            $daysRemaining = null;
            $status = 'COMPLIANT';
            $actionRequired = null;

            if ($expiresAt !== null) {
                $daysRemaining = (int) $asOf->diffInDays($expiresAt, false);

                if ($daysRemaining < 0) {
                    $status = 'EXPIRED';
                    $expiredCount++;
                    $actionRequired = 'HENTIKAN OPERASI PIT SEGERA: Perpanjangan izin wajib disetujui instansi berwenang.';
                } elseif ($daysRemaining <= 60) {
                    $status = 'CRITICAL_RENEWAL';
                    $criticalWarningCount++;
                    $actionRequired = "Batas waktu tersisa {$daysRemaining} hari. Ajukan berkas perpanjangan ke {$permit->issuing_authority}.";
                } elseif ($daysRemaining <= 180) {
                    $status = 'RENEWAL_WINDOW';
                    $actionRequired = 'Siapkan kelengkapan administrasi dan laporan teknis eksplorasi lanjutan.';
                    $compliantCount++;
                } else {
                    $compliantCount++;
                }
            } else {
                $compliantCount++;
            }

            // Validasi status dokumen di database
            if ($permit->status === 'revoked' || $permit->status === 'suspended') {
                $status = 'SUSPENDED';
                $actionRequired = 'Izin ditangguhkan oleh inspektur tambang atau pengawas KLHK.';
            }

            $auditSummary[] = [
                'permit_code' => $permit->permit_code ?? 'PRM-'.substr((string) $permit->id, 0, 8),
                'permit_type' => $permit->permit_type ?? 'IUP_OP',
                'issuing_authority' => $permit->issuing_authority ?? 'Kementerian ESDM',
                'expires_at' => $expiresAt?->toDateString(),
                'days_remaining' => $daysRemaining,
                'compliance_status' => $status,
                'action_required' => $actionRequired,
            ];
        }

        $isFullyCompliant = ($expiredCount === 0 && $criticalWarningCount === 0);

        return [
            'site_id' => (string) $site->id,
            'total_permits_audited' => $permits->count(),
            'compliant_count' => $compliantCount,
            'critical_warning_count' => $criticalWarningCount,
            'expired_count' => $expiredCount,
            'is_fully_compliant' => $isFullyCompliant,
            'audit_summary' => $auditSummary,
        ];
    }
}
