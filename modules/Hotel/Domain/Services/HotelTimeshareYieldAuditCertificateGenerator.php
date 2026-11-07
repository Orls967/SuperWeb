<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;

class HotelTimeshareYieldAuditCertificateGenerator
{
    /**
     * Menerbitkan sertifikat audit independen atas dividen hasil rental pool vila fraksional.
     *
     * @return array{certificate_id: string, timeshare_unit_id: string, period_label: string, total_pool_gross_idr: int, total_dividends_paid_idr: int, audit_hash: string, certified_at: string}
     */
    public function generateAuditCertificate(
        string $timeshareUnitId,
        string $periodLabel,
        int $grossPoolIdr,
        int $dividendsPaidIdr
    ): array {
        $certId = 'CERT-TS-AUDIT-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $hash = hash('sha256', "{$certId}:{$timeshareUnitId}:{$grossPoolIdr}:{$dividendsPaidIdr}");

        return [
            'certificate_id' => $certId,
            'timeshare_unit_id' => $timeshareUnitId,
            'period_label' => $periodLabel,
            'total_pool_gross_idr' => $grossPoolIdr,
            'total_dividends_paid_idr' => $dividendsPaidIdr,
            'audit_hash' => $hash,
            'certified_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
